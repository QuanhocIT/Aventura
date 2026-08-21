<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Activity,
    ArrowLeftRight,
    Ban,
    CalendarClock,
    Check,
    CheckCircle2,
    ClipboardCheck,
    Clock3,
    Eye,
    FileText,
    Filter,
    KeyRound,
    ListTodo,
    MapPin,
    PackageCheck,
    PackageOpen,
    Plus,
    RefreshCw,
    Route as RouteIcon,
    Search,
    Timer,
    Truck,
    X,
    XCircle,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

type TransferStatus =
    | 'requested'
    | 'routed'
    | 'dispatched'
    | 'received'
    | 'discrepancy'
    | 'rejected'
    | 'cancelled';

interface Transfer {
    id: number;
    status: TransferStatus;
    ingredient_id: number;
    ingredient: string | null;
    unit: string;
    to_branch_id: number;
    to_branch: string | null;
    from_branch_id: number | null;
    from_branch: string | null;
    quantity_requested: number;
    quantity_dispatched: number | null;
    quantity_received: number | null;
    quantity_remaining: number;
    discrepancy_quantity: number;
    source_available_quantity: number;
    source_unit_cost: number;
    reason: string;
    owner_note: string | null;
    dispatch_note: string | null;
    received_condition: string | null;
    received_note: string | null;
    receiving_evidence_path: string | null;
    discrepancy_reason: string | null;
    discrepancy_resolution: string | null;
    handover_code: string | null;
    requested_by: string | null;
    routed_by: string | null;
    dispatched_by: string | null;
    received_by: string | null;
    discrepancy_resolved_by: string | null;
    reject_reason: string | null;
    cancel_reason: string | null;
    created_at: string;
    routed_at: string | null;
    dispatched_at: string | null;
    received_at: string | null;
    can_route: boolean;
    can_dispatch: boolean;
    can_receive: boolean;
    can_resolve: boolean;
    can_cancel: boolean;
}

interface Branch {
    id: number;
    name: string;
}

interface IngredientOption {
    id: number;
    name: string;
    branch_id: number | null;
    unit: string;
}

const props = defineProps<{
    transfers: Transfer[];
    branches: Branch[];
    ingredients: IngredientOption[];
    permissions: { can_route: boolean; can_create: boolean };
    summary: {
        requested: number;
        routed: number;
        dispatched: number;
        discrepancy: number;
        completed: number;
    };
}>();

const showRequest = ref(false);
const routing = ref<Transfer | null>(null);
const dispatching = ref<Transfer | null>(null);
const receiving = ref<Transfer | null>(null);
const resolving = ref<Transfer | null>(null);
const cancelling = ref<Transfer | null>(null);
const rejecting = ref<Transfer | null>(null);
const detailTransfer = ref<Transfer | null>(null);
const search = ref('');
const statusFilter = ref<'all' | TransferStatus>('all');
const branchFilter = ref<number | 'all'>('all');
const workQueueOnly = ref(false);

const requestForm = useForm({
    to_branch_id: props.branches[0]?.id ?? ('' as number | ''),
    ingredient_id: '' as number | '',
    quantity_requested: 0,
    reason: '',
});

const routeForm = useForm({
    from_branch_id: '' as number | '',
    owner_note: '',
});

const dispatchForm = useForm({
    quantity_dispatched: 0,
    dispatch_note: '',
});

const receiveForm = useForm({
    handover_code: '',
    quantity_received: 0,
    received_condition: 'good',
    received_note: '',
    receiving_evidence: null as File | null,
});

const resolutionForm = useForm({ discrepancy_resolution: '' });
const cancelForm = useForm({ cancel_reason: '' });
const rejectForm = useForm({ reject_reason: '' });

const selectedIngredient = computed(() =>
    availableIngredients.value.find(
        (ingredient) =>
            Number(ingredient.id) === Number(requestForm.ingredient_id),
    ),
);

const availableIngredients = computed(() =>
    props.ingredients.filter(
        (ingredient) =>
            ingredient.branch_id === null ||
            Number(ingredient.branch_id) === Number(requestForm.to_branch_id),
    ),
);

const parseDate = (value: string | null): Date | null => {
    if (!value) {
        return null;
    }

    const vietnameseDate = value.match(
        /^(\d{2})\/(\d{2})\/(\d{4})\s+(\d{2}):(\d{2})$/,
    );
    const parsed = vietnameseDate
        ? new Date(
              Number(vietnameseDate[3]),
              Number(vietnameseDate[2]) - 1,
              Number(vietnameseDate[1]),
              Number(vietnameseDate[4]),
              Number(vietnameseDate[5]),
          )
        : new Date(value);

    return Number.isNaN(parsed.getTime()) ? null : parsed;
};

const statusStartedAt = (transfer: Transfer): string | null => {
    if (transfer.status === 'requested') {
        return transfer.created_at;
    }
    if (transfer.status === 'routed') {
        return transfer.routed_at;
    }
    if (transfer.status === 'dispatched') {
        return transfer.dispatched_at;
    }
    if (transfer.status === 'discrepancy') {
        return transfer.received_at;
    }

    return null;
};

const slaHours: Partial<Record<TransferStatus, number>> = {
    requested: 4,
    routed: 8,
    dispatched: 24,
    discrepancy: 24,
};

const ageInHours = (transfer: Transfer) => {
    const startedAt = parseDate(statusStartedAt(transfer));
    if (!startedAt) {
        return 0;
    }

    return Math.max(0, (Date.now() - startedAt.getTime()) / 3_600_000);
};

const isOverdue = (transfer: Transfer) => {
    const limit = slaHours[transfer.status];
    return limit !== undefined && ageInHours(transfer) > limit;
};

const overdueHours = (transfer: Transfer) =>
    Math.max(0, Math.round(ageInHours(transfer) - (slaHours[transfer.status] ?? 0)));

const needsAction = (transfer: Transfer) =>
    transfer.can_route ||
    transfer.can_dispatch ||
    transfer.can_receive ||
    transfer.can_resolve;

const nextAction = (transfer: Transfer) => {
    if (transfer.can_route) return 'Định tuyến nguồn cấp';
    if (transfer.can_dispatch) return 'Xác nhận xuất kho';
    if (transfer.can_receive) return 'Kiểm đếm & nhận hàng';
    if (transfer.can_resolve) return 'Chốt chênh lệch';
    return 'Theo dõi tiến độ';
};

const operationalStats = computed(() => {
    const active = props.transfers.filter((transfer) =>
        ['requested', 'routed', 'dispatched', 'discrepancy'].includes(
            transfer.status,
        ),
    );
    const completed = props.transfers.filter(
        (transfer) => transfer.status === 'received',
    );
    const inTransitValue = active
        .filter((transfer) => transfer.status === 'dispatched')
        .reduce(
            (total, transfer) =>
                total +
                (transfer.quantity_dispatched ?? 0) *
                    (transfer.source_unit_cost ?? 0),
            0,
        );
    const discrepancyValue = props.transfers.reduce(
        (total, transfer) =>
            total +
            transfer.discrepancy_quantity *
                (transfer.source_unit_cost ?? 0),
        0,
    );
    const cycleTimes = completed
        .map((transfer) => {
            const createdAt = parseDate(transfer.created_at);
            const receivedAt = parseDate(transfer.received_at);
            return createdAt && receivedAt
                ? (receivedAt.getTime() - createdAt.getTime()) / 3_600_000
                : null;
        })
        .filter((hours): hours is number => hours !== null && hours >= 0);

    return {
        activeCount: active.length,
        actionableCount: props.transfers.filter(needsAction).length,
        overdueCount: active.filter(isOverdue).length,
        inTransitValue,
        discrepancyValue,
        averageCycleHours: cycleTimes.length
            ? cycleTimes.reduce((total, hours) => total + hours, 0) /
              cycleTimes.length
            : 0,
    };
});

const workQueue = computed(() =>
    props.transfers
        .filter(needsAction)
        .sort((a, b) => {
            const overdueDiff = Number(isOverdue(b)) - Number(isOverdue(a));
            return overdueDiff || ageInHours(b) - ageInHours(a);
        })
        .slice(0, 5),
);

const timelineFor = (transfer: Transfer) => [
    {
        label: 'Yêu cầu',
        at: transfer.created_at,
        by: transfer.requested_by,
        done: true,
    },
    {
        label: 'Định tuyến',
        at: transfer.routed_at,
        by: transfer.routed_by,
        done: Boolean(transfer.routed_at),
    },
    {
        label: 'Xuất kho',
        at: transfer.dispatched_at,
        by: transfer.dispatched_by,
        done: Boolean(transfer.dispatched_at),
    },
    {
        label: 'Nhận hàng',
        at: transfer.received_at,
        by: transfer.received_by,
        done: Boolean(transfer.received_at),
    },
];

const filteredTransfers = computed(() => {
    const query = search.value.trim().toLowerCase();

    return props.transfers.filter((transfer) => {
        const matchesStatus =
            statusFilter.value === 'all' ||
            transfer.status === statusFilter.value;
        const matchesBranch =
            branchFilter.value === 'all' ||
            transfer.from_branch_id === branchFilter.value ||
            transfer.to_branch_id === branchFilter.value;
        const matchesQueue = !workQueueOnly.value || needsAction(transfer);
        const haystack = [
            transfer.ingredient,
            transfer.from_branch,
            transfer.to_branch,
            transfer.reason,
            String(transfer.id),
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase();

        return (
            matchesStatus &&
            matchesBranch &&
            matchesQueue &&
            (!query || haystack.includes(query))
        );
    });
});

const statusConfig: Record<
    TransferStatus,
    { label: string; className: string; icon: typeof Clock3 }
> = {
    requested: {
        label: 'Chờ định tuyến',
        className: 'border-blue-400/30 bg-blue-500/10 text-blue-300',
        icon: Clock3,
    },
    routed: {
        label: 'Chờ xuất kho',
        className: 'border-amber-400/30 bg-amber-500/10 text-amber-300',
        icon: RouteIcon,
    },
    dispatched: {
        label: 'Đang vận chuyển',
        className: 'border-violet-400/30 bg-violet-500/10 text-violet-300',
        icon: Truck,
    },
    discrepancy: {
        label: 'Chờ xử lý chênh lệch',
        className: 'border-rose-400/30 bg-rose-500/10 text-rose-300',
        icon: AlertTriangle,
    },
    received: {
        label: 'Đã hoàn tất',
        className: 'border-emerald-400/30 bg-emerald-500/10 text-emerald-300',
        icon: CheckCircle2,
    },
    rejected: {
        label: 'Đã từ chối',
        className: 'border-rose-400/30 bg-rose-500/10 text-rose-300',
        icon: XCircle,
    },
    cancelled: {
        label: 'Đã hủy',
        className: 'border-slate-400/30 bg-slate-500/10 text-slate-400',
        icon: Ban,
    },
};

const openRoute = (transfer: Transfer) => {
    detailTransfer.value = null;
    routing.value = transfer;
    routeForm.reset();
};

const openDispatch = (transfer: Transfer) => {
    detailTransfer.value = null;
    dispatching.value = transfer;
    dispatchForm.quantity_dispatched = transfer.quantity_requested;
    dispatchForm.dispatch_note = '';
};

const openReceive = (transfer: Transfer) => {
    detailTransfer.value = null;
    receiving.value = transfer;
    receiveForm.handover_code = '';
    receiveForm.quantity_received =
        transfer.quantity_dispatched ?? transfer.quantity_requested;
    receiveForm.received_condition = 'good';
    receiveForm.received_note = '';
    receiveForm.receiving_evidence = null;
};

const openResolve = (transfer: Transfer) => {
    detailTransfer.value = null;
    resolving.value = transfer;
    resolutionForm.reset();
};

const openCancel = (transfer: Transfer) => {
    detailTransfer.value = null;
    cancelling.value = transfer;
    cancelForm.reset();
};

const openReject = (transfer: Transfer) => {
    detailTransfer.value = null;
    rejecting.value = transfer;
    rejectForm.reset();
};

const closeModals = () => {
    routing.value = null;
    dispatching.value = null;
    receiving.value = null;
    resolving.value = null;
    cancelling.value = null;
    rejecting.value = null;
    detailTransfer.value = null;
};

const openDetails = (transfer: Transfer) => {
    detailTransfer.value = transfer;
};

const setStatusFilter = (status: 'all' | TransferStatus) => {
    statusFilter.value = status;
    workQueueOnly.value = false;
};

const setWorkQueue = () => {
    workQueueOnly.value = true;
    statusFilter.value = 'all';
};

const refreshPage = () => {
    router.reload({ preserveScroll: true });
};

watch(
    () => requestForm.to_branch_id,
    () => {
        if (!selectedIngredient.value) {
            requestForm.ingredient_id = '';
        }
    },
);

const submitRequest = () => {
    if (requestForm.processing) {
        return;
    }

    requestForm.post('/inventory/transfers', {
        preserveScroll: true,
        onSuccess: () => {
            requestForm.reset();
            showRequest.value = false;
        },
    });
};

const submitRoute = () => {
    if (!routing.value || routeForm.processing) {
        return;
    }

    routeForm.post(`/inventory/transfers/${routing.value.id}/route`, {
        preserveScroll: true,
        onSuccess: closeModals,
    });
};

const submitDispatch = () => {
    if (!dispatching.value || dispatchForm.processing) {
        return;
    }

    dispatchForm.post(`/inventory/transfers/${dispatching.value.id}/dispatch`, {
        preserveScroll: true,
        onSuccess: closeModals,
    });
};

const submitReceive = () => {
    if (!receiving.value || receiveForm.processing) {
        return;
    }

    receiveForm.post(`/inventory/transfers/${receiving.value.id}/receive`, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: closeModals,
    });
};

const submitResolution = () => {
    if (!resolving.value || resolutionForm.processing) {
        return;
    }

    resolutionForm.post(
        `/inventory/transfers/${resolving.value.id}/resolve-discrepancy`,
        { preserveScroll: true, onSuccess: closeModals },
    );
};

const submitCancel = () => {
    if (!cancelling.value || cancelForm.processing) {
        return;
    }

    cancelForm.post(`/inventory/transfers/${cancelling.value.id}/cancel`, {
        preserveScroll: true,
        onSuccess: closeModals,
    });
};

const submitReject = () => {
    if (!rejecting.value || rejectForm.processing) {
        return;
    }

    rejectForm.post(`/inventory/transfers/${rejecting.value.id}/reject`, {
        preserveScroll: true,
        onSuccess: closeModals,
    });
};

const onEvidenceChange = (event: Event) => {
    const input = event.target as HTMLInputElement;
    receiveForm.receiving_evidence = input.files?.[0] ?? null;
};

const formatNumber = (value: number | null) =>
    value === null
        ? '—'
        : new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 3 }).format(
          value,
      );

const formatCurrency = (value: number) =>
    new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
        maximumFractionDigits: 0,
    }).format(value || 0);

const formatDuration = (hours: number) => {
    if (!hours) return '—';
    if (hours < 24) return `${Math.round(hours)} giờ`;
    return `${Math.floor(hours / 24)} ngày ${Math.round(hours % 24)} giờ`;
};
</script>

<template>
    <Head title="Điều chuyển liên chi nhánh" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-5 p-4 sm:p-6">
        <section
            class="flex flex-col gap-4 rounded-2xl border border-teal-400/20 bg-gradient-to-r from-slate-950 via-slate-900 to-teal-950/70 p-5 text-white shadow-xl sm:flex-row sm:items-center sm:justify-between sm:p-6"
        >
            <div class="flex items-start gap-3">
                <div
                    class="flex size-12 shrink-0 items-center justify-center rounded-xl border border-teal-300/30 bg-teal-400/15 text-teal-200"
                >
                    <ArrowLeftRight class="size-6" />
                </div>
                <div>
                    <p
                        class="text-[10px] font-bold tracking-[0.22em] text-teal-300/80 uppercase"
                    >
                        Kho vận hành
                    </p>
                    <h1
                        class="mt-1 text-xl font-black tracking-tight sm:text-2xl"
                    >
                        Điều chuyển liên chi nhánh
                    </h1>
                    <p
                        class="mt-1 max-w-2xl text-xs leading-5 text-teal-100/70"
                    >
                        Theo dõi đủ chu trình yêu cầu, định tuyến nguồn, xuất
                        kho, bàn giao, nhận thực tế và xử lý chênh lệch.
                    </p>
                </div>
            </div>
            <Button
                v-if="props.permissions.can_create"
                @click="showRequest = !showRequest"
                class="shrink-0 gap-1.5 rounded-xl border-0 bg-teal-500 font-bold text-white shadow-lg shadow-teal-950/30 hover:bg-teal-400"
            >
                <Plus class="size-4" /> Tạo yêu cầu
            </Button>
        </section>

        <section class="grid grid-cols-2 gap-3 lg:grid-cols-5">
            <button
                type="button"
                @click="setStatusFilter('requested')"
                class="rounded-2xl border border-blue-400/15 bg-blue-950/15 p-4"
            >
                <p
                    class="text-[10px] font-bold tracking-wider text-blue-300 uppercase"
                >
                    Chờ định tuyến
                </p>
                <p class="mt-2 text-2xl font-black text-white">
                    {{ props.summary.requested }}
                </p>
                <p class="mt-1 text-[11px] text-muted-foreground">
                    Cần chọn kho cấp
                </p>
            </button>
            <button
                type="button"
                @click="setStatusFilter('routed')"
                class="rounded-2xl border border-amber-400/15 bg-amber-950/15 p-4"
            >
                <p
                    class="text-[10px] font-bold tracking-wider text-amber-300 uppercase"
                >
                    Chờ xuất
                </p>
                <p class="mt-2 text-2xl font-black text-white">
                    {{ props.summary.routed }}
                </p>
                <p class="mt-1 text-[11px] text-muted-foreground">
                    Đã có mã giao nhận
                </p>
            </button>
            <button
                type="button"
                @click="setStatusFilter('dispatched')"
                class="rounded-2xl border border-violet-400/15 bg-violet-950/15 p-4"
            >
                <p
                    class="text-[10px] font-bold tracking-wider text-violet-300 uppercase"
                >
                    Đang vận chuyển
                </p>
                <p class="mt-2 text-2xl font-black text-white">
                    {{ props.summary.dispatched }}
                </p>
                <p class="mt-1 text-[11px] text-muted-foreground">
                    Chờ chi nhánh nhận
                </p>
            </button>
            <button
                type="button"
                @click="setStatusFilter('discrepancy')"
                class="rounded-2xl border border-rose-400/15 bg-rose-950/15 p-4"
            >
                <p
                    class="text-[10px] font-bold tracking-wider text-rose-300 uppercase"
                >
                    Chênh lệch
                </p>
                <p class="mt-2 text-2xl font-black text-white">
                    {{ props.summary.discrepancy }}
                </p>
                <p class="mt-1 text-[11px] text-muted-foreground">
                    Cần lập biên bản
                </p>
            </button>
            <button
                type="button"
                @click="setStatusFilter('received')"
                class="col-span-2 rounded-2xl border border-emerald-400/15 bg-emerald-950/15 p-4 lg:col-span-1"
            >
                <p
                    class="text-[10px] font-bold tracking-wider text-emerald-300 uppercase"
                >
                    Đã hoàn tất
                </p>
                <p class="mt-2 text-2xl font-black text-white">
                    {{ props.summary.completed }}
                </p>
                <p class="mt-1 text-[11px] text-muted-foreground">
                    Đã đối soát xong
                </p>
            </button>
        </section>

        <section
            class="grid gap-4 xl:grid-cols-[1.35fr_1fr]"
            aria-label="Trung tâm điều hành điều chuyển"
        >
            <div class="rounded-2xl border border-border bg-card/70 p-4 shadow-sm sm:p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <ListTodo class="size-4 text-teal-400" />
                            <h2 class="font-black text-foreground">Việc cần xử lý</h2>
                        </div>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Ưu tiên các phiếu đang chờ người dùng hiện tại thao tác.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-teal-400/20 px-2.5 py-1.5 text-xs font-bold text-teal-300 transition hover:bg-teal-400/10"
                        @click="setWorkQueue"
                    >
                        <Filter class="size-3.5" /> Xem toàn bộ hàng đợi
                    </button>
                </div>

                <div v-if="workQueue.length" class="mt-4 space-y-2">
                    <div
                        v-for="transfer in workQueue"
                        :key="`queue-${transfer.id}`"
                        class="flex flex-col gap-3 rounded-xl border border-border/80 bg-background/60 p-3 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <button
                            type="button"
                            class="min-w-0 text-left"
                            @click="openDetails(transfer)"
                        >
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-mono text-[10px] font-bold text-muted-foreground">TR-{{ String(transfer.id).padStart(5, '0') }}</span>
                                <span class="truncate text-sm font-bold text-foreground">{{ transfer.ingredient }}</span>
                                <span
                                    v-if="isOverdue(transfer)"
                                    class="inline-flex items-center gap-1 rounded-full border border-rose-400/30 bg-rose-500/10 px-2 py-0.5 text-[10px] font-bold text-rose-300"
                                >
                                    <Timer class="size-3" /> Quá SLA {{ overdueHours(transfer) }}h
                                </span>
                            </div>
                            <p class="mt-1 truncate text-xs text-muted-foreground">
                                {{ transfer.from_branch || 'Chưa chọn nguồn' }} → {{ transfer.to_branch }} · {{ nextAction(transfer) }}
                            </p>
                        </button>
                        <div class="flex shrink-0 items-center gap-2">
                            <span class="text-xs font-semibold text-muted-foreground">{{ formatNumber(transfer.quantity_requested) }} {{ transfer.unit }}</span>
                            <Button size="sm" class="gap-1.5 bg-teal-600 font-bold text-white hover:bg-teal-500" @click="openDetails(transfer)">
                                Xử lý <Activity class="size-3.5" />
                            </Button>
                        </div>
                    </div>
                </div>
                <div v-else class="mt-4 rounded-xl border border-dashed border-emerald-400/20 bg-emerald-950/10 p-5 text-center">
                    <CheckCircle2 class="mx-auto size-6 text-emerald-400" />
                    <p class="mt-2 text-sm font-bold text-foreground">Không còn việc tồn</p>
                    <p class="mt-1 text-xs text-muted-foreground">Các phiếu thuộc quyền của bạn đã được xử lý hoặc đang chờ bước tiếp theo.</p>
                </div>
            </div>

            <div class="rounded-2xl border border-border bg-card/70 p-4 shadow-sm sm:p-5">
                <div class="flex items-center gap-2">
                    <Activity class="size-4 text-indigo-400" />
                    <h2 class="font-black text-foreground">Sức khỏe luồng điều chuyển</h2>
                </div>
                <p class="mt-1 text-xs text-muted-foreground">Tổng hợp theo các phiếu đang hiển thị trong phạm vi dữ liệu của bạn.</p>
                <div class="mt-4 grid grid-cols-2 gap-2">
                    <div class="rounded-xl bg-muted/40 p-3">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Đang mở</p>
                        <p class="mt-1 text-lg font-black text-foreground">{{ operationalStats.activeCount }}</p>
                        <p class="text-[11px] text-muted-foreground">phiếu chưa đối soát</p>
                    </div>
                    <div class="rounded-xl bg-rose-500/5 p-3">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-rose-300">Quá SLA</p>
                        <p class="mt-1 text-lg font-black text-rose-300">{{ operationalStats.overdueCount }}</p>
                        <p class="text-[11px] text-muted-foreground">cần đôn đốc</p>
                    </div>
                    <div class="rounded-xl bg-violet-500/5 p-3">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-violet-300">Giá trị đang đi</p>
                        <p class="mt-1 text-lg font-black text-violet-200">{{ formatCurrency(operationalStats.inTransitValue) }}</p>
                        <p class="text-[11px] text-muted-foreground">tạm tính theo giá xuất</p>
                    </div>
                    <div class="rounded-xl bg-amber-500/5 p-3">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-amber-300">Chênh lệch</p>
                        <p class="mt-1 text-lg font-black text-amber-200">{{ formatCurrency(operationalStats.discrepancyValue) }}</p>
                        <p class="text-[11px] text-muted-foreground">giá trị cần xác minh</p>
                    </div>
                </div>
                <div class="mt-3 flex items-center gap-2 rounded-xl border border-border/70 px-3 py-2.5 text-xs text-muted-foreground">
                    <CalendarClock class="size-4 text-teal-400" />
                    <span>Thời gian hoàn tất trung bình:</span>
                    <strong class="text-foreground">{{ formatDuration(operationalStats.averageCycleHours) }}</strong>
                </div>
            </div>
        </section>

        <section
            v-if="showRequest"
            class="rounded-2xl border border-teal-400/20 bg-card/80 p-5 shadow-sm"
        >
            <div class="mb-4 flex items-center justify-between gap-3">
                <div>
                    <h2 class="font-black text-foreground">
                        Tạo yêu cầu điều chuyển
                    </h2>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Yêu cầu sẽ nằm ở trạng thái chờ định tuyến cho đến khi
                        kho cấp được chọn.
                    </p>
                </div>
                <Button variant="ghost" size="icon" @click="showRequest = false"
                    ><X class="size-4"
                /></Button>
            </div>
            <form
                @submit.prevent="submitRequest"
                class="grid grid-cols-1 gap-4 md:grid-cols-2"
            >
                <div class="flex flex-col gap-1.5">
                    <Label>Chi nhánh cần hàng</Label>
                    <select
                        v-model="requestForm.to_branch_id"
                        required
                        class="h-10 rounded-md border border-input bg-background px-3 text-sm text-foreground"
                    >
                        <option
                            v-for="branch in props.branches"
                            :key="branch.id"
                            :value="branch.id"
                        >
                            {{ branch.name }}
                        </option>
                    </select>
                    <p
                        v-if="requestForm.errors.to_branch_id"
                        class="text-xs text-rose-500"
                    >
                        {{ requestForm.errors.to_branch_id }}
                    </p>
                </div>
                <div class="flex flex-col gap-1.5">
                    <Label>Nguyên liệu</Label>
                    <select
                        v-model="requestForm.ingredient_id"
                        required
                        class="h-10 rounded-md border border-input bg-background px-3 text-sm text-foreground"
                    >
                        <option value="" disabled>— Chọn nguyên liệu —</option>
                        <option
                            v-for="ingredient in availableIngredients"
                            :key="ingredient.id"
                            :value="ingredient.id"
                        >
                            {{ ingredient.name }} ({{ ingredient.unit }})
                        </option>
                    </select>
                    <p
                        v-if="requestForm.errors.ingredient_id"
                        class="text-xs text-rose-500"
                    >
                        {{ requestForm.errors.ingredient_id }}
                    </p>
                </div>
                <div class="flex flex-col gap-1.5">
                    <Label>Số lượng cần</Label>
                    <Input
                        v-model="requestForm.quantity_requested"
                        type="number"
                        step="0.001"
                        min="0.001"
                        required
                    />
                    <p
                        v-if="selectedIngredient"
                        class="text-[11px] text-muted-foreground"
                    >
                        Đơn vị: {{ selectedIngredient.unit }}
                    </p>
                    <p v-else class="text-[11px] text-muted-foreground">
                        Chỉ hiển thị nguyên liệu dùng được tại chi nhánh nhận.
                    </p>
                    <p
                        v-if="requestForm.errors.quantity_requested"
                        class="text-xs text-rose-500"
                    >
                        {{ requestForm.errors.quantity_requested }}
                    </p>
                </div>
                <div class="flex flex-col gap-1.5">
                    <Label>Lý do / nhu cầu vận hành</Label>
                    <Input
                        v-model="requestForm.reason"
                        required
                        placeholder="VD: Dự báo thiếu cho ca tối, hỏng hàng, tồn dưới định mức..."
                    />
                    <p
                        v-if="requestForm.errors.reason"
                        class="text-xs text-rose-500"
                    >
                        {{ requestForm.errors.reason }}
                    </p>
                </div>
                <div class="flex justify-end gap-2 md:col-span-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="showRequest = false"
                        >Hủy</Button
                    >
                    <Button
                        type="submit"
                        :disabled="requestForm.processing"
                        class="border-0 bg-teal-500 font-bold text-white hover:bg-teal-400"
                        >Gửi yêu cầu</Button
                    >
                </div>
            </form>
        </section>

        <section
            class="flex flex-col gap-3 rounded-2xl border border-border bg-card/50 p-3 sm:flex-row sm:items-center"
        >
            <div class="relative flex-1">
                <Search
                    class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="search"
                    class="pl-9"
                    placeholder="Tìm theo mã, nguyên liệu, chi nhánh, lý do..."
                />
            </div>
            <select
                v-model="statusFilter"
                class="h-10 rounded-md border border-input bg-background px-3 text-sm text-foreground sm:w-56"
            >
                <option value="all">Tất cả trạng thái</option>
                <option value="requested">Chờ định tuyến</option>
                <option value="routed">Chờ xuất kho</option>
                <option value="dispatched">Đang vận chuyển</option>
                <option value="discrepancy">Chờ xử lý chênh lệch</option>
                <option value="received">Đã hoàn tất</option>
                <option value="rejected">Đã từ chối</option>
                <option value="cancelled">Đã hủy</option>
            </select>
            <select
                v-model="branchFilter"
                class="h-10 rounded-md border border-input bg-background px-3 text-sm text-foreground sm:w-52"
            >
                <option value="all">Tất cả chi nhánh</option>
                <option
                    v-for="branch in props.branches"
                    :key="`filter-${branch.id}`"
                    :value="branch.id"
                >
                    {{ branch.name }}
                </option>
            </select>
            <button
                v-if="workQueueOnly"
                type="button"
                class="inline-flex h-10 items-center justify-center gap-1.5 rounded-md border border-teal-400/30 bg-teal-400/10 px-3 text-xs font-bold text-teal-300"
                @click="workQueueOnly = false"
            >
                <ListTodo class="size-3.5" /> Hàng đợi của tôi
            </button>
            <Button
                type="button"
                variant="outline"
                size="icon"
                title="Làm mới dữ liệu"
                @click="refreshPage"
            >
                <RefreshCw class="size-4" />
            </Button>
            <span
                class="text-xs font-semibold whitespace-nowrap text-muted-foreground"
                >{{ filteredTransfers.length }} /
                {{ props.transfers.length }} yêu cầu</span
            >
        </section>

        <section v-if="filteredTransfers.length" class="flex flex-col gap-3">
            <article
                v-for="transfer in filteredTransfers"
                :key="transfer.id"
                class="rounded-2xl border border-border bg-card/80 p-4 shadow-sm"
            >
                <div
                    class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between"
                >
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span
                                class="font-mono text-[11px] font-bold text-muted-foreground"
                                >TR-{{
                                    String(transfer.id).padStart(5, '0')
                                }}</span
                            >
                            <span class="text-lg font-black text-foreground">{{
                                transfer.ingredient
                            }}</span>
                            <span
                                class="rounded-full border px-2 py-1 text-[10px] font-bold"
                                :class="statusConfig[transfer.status].className"
                            >
                                <component
                                    :is="statusConfig[transfer.status].icon"
                                    class="mr-1 inline size-3"
                                />{{ statusConfig[transfer.status].label }}
                            </span>
                            <span
                                v-if="isOverdue(transfer)"
                                class="inline-flex items-center gap-1 rounded-full border border-rose-400/30 bg-rose-500/10 px-2 py-1 text-[10px] font-bold text-rose-300"
                            >
                                <Timer class="size-3" /> Quá SLA {{ overdueHours(transfer) }}h
                            </span>
                        </div>
                        <div
                            class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground"
                        >
                            <span class="inline-flex items-center gap-1"
                                ><MapPin class="size-3" />{{
                                    transfer.from_branch || 'Chưa chọn nguồn'
                                }}
                                → {{ transfer.to_branch }}</span
                            >
                            <span class="inline-flex items-center gap-1"
                                ><Clock3 class="size-3" />{{
                                    transfer.created_at
                                }}</span
                            >
                            <span
                                >Người yêu cầu:
                                {{ transfer.requested_by || '—' }}</span
                            >
                        </div>
                        <p class="mt-2 text-sm text-muted-foreground">
                            {{ transfer.reason }}
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <Button
                            size="sm"
                            variant="outline"
                            @click="openDetails(transfer)"
                            class="gap-1.5"
                            ><Eye class="size-3.5" /> Hồ sơ</Button
                        >
                        <Button
                            v-if="transfer.can_route"
                            size="sm"
                            @click="openRoute(transfer)"
                            class="gap-1.5 bg-indigo-600 font-bold text-white hover:bg-indigo-700"
                            ><RouteIcon class="size-3.5" /> Định tuyến</Button
                        >
                        <Button
                            v-if="transfer.can_dispatch"
                            size="sm"
                            @click="openDispatch(transfer)"
                            class="gap-1.5 bg-amber-600 font-bold text-white hover:bg-amber-700"
                            ><PackageOpen class="size-3.5" /> Xuất kho</Button
                        >
                        <Button
                            v-if="transfer.can_receive"
                            size="sm"
                            @click="openReceive(transfer)"
                            class="gap-1.5 bg-emerald-600 font-bold text-white hover:bg-emerald-700"
                            ><PackageCheck class="size-3.5" /> Nhận hàng</Button
                        >
                        <Button
                            v-if="transfer.can_resolve"
                            size="sm"
                            @click="openResolve(transfer)"
                            class="gap-1.5 bg-rose-600 font-bold text-white hover:bg-rose-700"
                            ><ClipboardCheck class="size-3.5" /> Chốt chênh
                            lệch</Button
                        >
                        <Button
                            v-if="transfer.can_cancel"
                            size="sm"
                            variant="outline"
                            @click="openCancel(transfer)"
                            class="gap-1.5 text-rose-500"
                            ><Ban class="size-3.5" /> Hủy</Button
                        >
                        <Button
                            v-if="
                                transfer.can_route &&
                                transfer.status === 'requested'
                            "
                            size="sm"
                            variant="outline"
                            @click="openReject(transfer)"
                            class="gap-1.5 text-rose-500"
                            ><XCircle class="size-3.5" /> Từ chối</Button
                        >
                    </div>
                </div>

                <div
                    class="mt-4 grid grid-cols-2 gap-2 border-t border-border pt-4 md:grid-cols-4"
                >
                    <div class="rounded-xl bg-muted/40 p-3">
                        <p
                            class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                        >
                            Yêu cầu
                        </p>
                        <p class="mt-1 font-black text-foreground">
                            {{ formatNumber(transfer.quantity_requested) }}
                            {{ transfer.unit }}
                        </p>
                    </div>
                    <div class="rounded-xl bg-muted/40 p-3">
                        <p
                            class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                        >
                            Đã xuất
                        </p>
                        <p class="mt-1 font-black text-foreground">
                            {{ formatNumber(transfer.quantity_dispatched) }}
                            {{ transfer.unit }}
                        </p>
                    </div>
                    <div class="rounded-xl bg-muted/40 p-3">
                        <p
                            class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                        >
                            Thực nhận
                        </p>
                        <p class="mt-1 font-black text-foreground">
                            {{ formatNumber(transfer.quantity_received) }}
                            {{ transfer.unit }}
                        </p>
                    </div>
                    <div class="rounded-xl bg-muted/40 p-3">
                        <p
                            class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                        >
                            Kho nguồn hiện có
                        </p>
                        <p class="mt-1 font-black text-foreground">
                            {{
                                formatNumber(transfer.source_available_quantity)
                            }}
                            {{ transfer.unit }}
                        </p>
                    </div>
                </div>

                <div
                    class="mt-4 flex items-center gap-1 text-[10px] font-bold text-muted-foreground"
                >
                    <span
                        :class="
                            transfer.status !== 'requested'
                                ? 'text-teal-400'
                                : 'text-teal-400'
                        "
                        ><Check class="mr-1 inline size-3" />Yêu cầu</span
                    >
                    <span
                        class="h-px flex-1 bg-border"
                        :class="transfer.from_branch ? 'bg-teal-500/60' : ''"
                    ></span>
                    <span
                        :class="
                            transfer.status !== 'requested'
                                ? 'text-teal-400'
                                : ''
                        "
                        ><Check class="mr-1 inline size-3" />Định tuyến</span
                    >
                    <span
                        class="h-px flex-1 bg-border"
                        :class="
                            ['dispatched', 'discrepancy', 'received'].includes(
                                transfer.status,
                            )
                                ? 'bg-teal-500/60'
                                : ''
                        "
                    ></span>
                    <span
                        :class="
                            ['dispatched', 'discrepancy', 'received'].includes(
                                transfer.status,
                            )
                                ? 'text-teal-400'
                                : ''
                        "
                        ><Truck class="mr-1 inline size-3" />Xuất</span
                    >
                    <span
                        class="h-px flex-1 bg-border"
                        :class="
                            ['received'].includes(transfer.status)
                                ? 'bg-teal-500/60'
                                : ''
                        "
                    ></span>
                    <span
                        :class="
                            transfer.status === 'received'
                                ? 'text-emerald-400'
                                : ''
                        "
                        ><CheckCircle2 class="mr-1 inline size-3" />Nhận</span
                    >
                </div>

                <div
                    v-if="
                        transfer.handover_code &&
                        ['dispatched', 'discrepancy'].includes(transfer.status)
                    "
                    class="mt-3 flex flex-wrap items-center gap-2 rounded-xl border border-violet-400/20 bg-violet-950/20 px-3 py-2 text-xs"
                >
                    <KeyRound class="size-4 text-violet-300" /><span
                        class="text-muted-foreground"
                        >Mã giao nhận:</span
                    ><strong
                        class="font-mono tracking-[0.25em] text-violet-200"
                        >{{ transfer.handover_code }}</strong
                    >
                </div>
                <div
                    v-if="
                        transfer.owner_note ||
                        transfer.dispatch_note ||
                        transfer.received_note ||
                        transfer.reject_reason ||
                        transfer.cancel_reason ||
                        transfer.discrepancy_resolution
                    "
                    class="mt-3 space-y-1 text-xs text-muted-foreground"
                >
                    <p v-if="transfer.owner_note">
                        <b>Điều phối:</b> {{ transfer.owner_note }}
                    </p>
                    <p v-if="transfer.dispatch_note">
                        <b>Xuất kho:</b> {{ transfer.dispatch_note }}
                    </p>
                    <p v-if="transfer.received_note">
                        <b>Biên bản nhận:</b> {{ transfer.received_note }}
                    </p>
                    <a
                        v-if="transfer.receiving_evidence_path"
                        :href="`/storage/${transfer.receiving_evidence_path}`"
                        target="_blank"
                        rel="noreferrer"
                        class="inline-flex items-center gap-1 font-semibold text-teal-400 hover:text-teal-300"
                    >
                        <FileText class="size-3" /> Xem bằng chứng nhận hàng
                    </a>
                    <p
                        v-if="transfer.discrepancy_quantity > 0"
                        class="font-semibold text-rose-400"
                    >
                        <b>Chênh lệch:</b> thiếu
                        {{ formatNumber(transfer.discrepancy_quantity) }}
                        {{ transfer.unit }}
                    </p>
                    <p v-if="transfer.discrepancy_resolution">
                        <b>Đã chốt:</b> {{ transfer.discrepancy_resolution }}
                    </p>
                    <p v-if="transfer.reject_reason" class="text-rose-400">
                        <b>Từ chối:</b> {{ transfer.reject_reason }}
                    </p>
                    <p v-if="transfer.cancel_reason" class="text-rose-400">
                        <b>Hủy:</b> {{ transfer.cancel_reason }}
                    </p>
                </div>
            </article>
        </section>
        <section
            v-else
            class="rounded-2xl border border-dashed border-border bg-card/40 p-12 text-center"
        >
            <FileText class="mx-auto size-8 text-muted-foreground/50" />
            <p class="mt-3 text-sm font-semibold text-muted-foreground">
                Không có yêu cầu phù hợp
            </p>
            <p class="mt-1 text-xs text-muted-foreground">
                Tạo yêu cầu mới hoặc thay đổi bộ lọc để xem dữ liệu.
            </p>
        </section>
    </div>

    <Teleport to="body">
    <div
        v-if="
            routing ||
            dispatching ||
            receiving ||
            resolving ||
            cancelling ||
            rejecting ||
            detailTransfer
        "
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 p-4 backdrop-blur-sm"
        @click.self="closeModals"
    >
        <div
            class="max-h-[92vh] w-full overflow-y-auto rounded-3xl border border-border bg-background p-5 shadow-2xl sm:p-6"
            :class="detailTransfer ? 'max-w-2xl' : 'max-w-lg'"
        >
            <template v-if="detailTransfer">
                <div class="mb-5 flex items-start justify-between gap-3">
                    <div>
                        <p class="text-[10px] font-bold tracking-wider text-teal-400 uppercase">Hồ sơ điều chuyển</p>
                        <div class="mt-1 flex flex-wrap items-center gap-2">
                            <h2 class="text-xl font-black">TR-{{ String(detailTransfer.id).padStart(5, '0') }}</h2>
                            <span
                                class="rounded-full border px-2 py-1 text-[10px] font-bold"
                                :class="statusConfig[detailTransfer.status].className"
                            >
                                {{ statusConfig[detailTransfer.status].label }}
                            </span>
                            <span v-if="isOverdue(detailTransfer)" class="rounded-full bg-rose-500/10 px-2 py-1 text-[10px] font-bold text-rose-300">Quá SLA {{ overdueHours(detailTransfer) }}h</span>
                        </div>
                        <p class="mt-1 text-xs text-muted-foreground">{{ detailTransfer.ingredient }} · {{ detailTransfer.from_branch || 'Chưa chọn nguồn' }} → {{ detailTransfer.to_branch }}</p>
                    </div>
                    <Button variant="ghost" size="icon" @click="closeModals"><X class="size-4" /></Button>
                </div>

                <div class="grid gap-3 sm:grid-cols-4">
                    <div class="rounded-xl bg-muted/40 p-3">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Yêu cầu</p>
                        <p class="mt-1 font-black text-foreground">{{ formatNumber(detailTransfer.quantity_requested) }} {{ detailTransfer.unit }}</p>
                    </div>
                    <div class="rounded-xl bg-muted/40 p-3">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Đã xuất</p>
                        <p class="mt-1 font-black text-foreground">{{ formatNumber(detailTransfer.quantity_dispatched) }} {{ detailTransfer.unit }}</p>
                    </div>
                    <div class="rounded-xl bg-muted/40 p-3">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Thực nhận</p>
                        <p class="mt-1 font-black text-foreground">{{ formatNumber(detailTransfer.quantity_received) }} {{ detailTransfer.unit }}</p>
                    </div>
                    <div class="rounded-xl bg-muted/40 p-3">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Giá trị tạm tính</p>
                        <p class="mt-1 font-black text-foreground">{{ formatCurrency((detailTransfer.quantity_dispatched ?? detailTransfer.quantity_requested) * detailTransfer.source_unit_cost) }}</p>
                    </div>
                </div>

                <div class="mt-5 rounded-2xl border border-border/80 p-4">
                    <div class="flex items-center gap-2">
                        <Activity class="size-4 text-teal-400" />
                        <h3 class="text-sm font-black text-foreground">Tiến trình & trách nhiệm</h3>
                    </div>
                    <div class="mt-4 grid gap-3 sm:grid-cols-4">
                        <div v-for="step in timelineFor(detailTransfer)" :key="step.label" class="relative rounded-xl border p-3" :class="step.done ? 'border-teal-400/25 bg-teal-400/5' : 'border-border/60 bg-muted/20 opacity-60'">
                            <div class="flex items-center gap-2">
                                <CheckCircle2 v-if="step.done" class="size-4 text-teal-400" />
                                <Clock3 v-else class="size-4 text-muted-foreground" />
                                <span class="text-xs font-bold text-foreground">{{ step.label }}</span>
                            </div>
                            <p class="mt-2 text-[11px] text-muted-foreground">{{ step.at || 'Chưa thực hiện' }}</p>
                            <p v-if="step.by" class="mt-1 truncate text-[11px] text-muted-foreground">{{ step.by }}</p>
                        </div>
                    </div>
                </div>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div class="rounded-xl border border-border/80 p-4">
                        <h3 class="text-xs font-black uppercase tracking-wider text-muted-foreground">Nội dung yêu cầu</h3>
                        <p class="mt-2 text-sm text-foreground">{{ detailTransfer.reason }}</p>
                        <p class="mt-2 text-xs text-muted-foreground">Người yêu cầu: {{ detailTransfer.requested_by || '—' }}</p>
                        <p v-if="detailTransfer.owner_note" class="mt-2 text-xs text-muted-foreground"><b>Điều phối:</b> {{ detailTransfer.owner_note }}</p>
                    </div>
                    <div class="rounded-xl border border-border/80 p-4">
                        <h3 class="text-xs font-black uppercase tracking-wider text-muted-foreground">Bàn giao & đối soát</h3>
                        <p v-if="detailTransfer.handover_code" class="mt-2 text-sm text-foreground">Mã giao nhận: <strong class="font-mono tracking-[0.2em] text-violet-300">{{ detailTransfer.handover_code }}</strong></p>
                        <p v-if="detailTransfer.received_condition" class="mt-2 text-xs text-muted-foreground">Tình trạng: {{ detailTransfer.received_condition }}</p>
                        <p v-if="detailTransfer.received_note" class="mt-2 text-xs text-muted-foreground">Biên bản: {{ detailTransfer.received_note }}</p>
                        <p v-if="detailTransfer.discrepancy_quantity > 0" class="mt-2 text-xs font-semibold text-rose-300">Thiếu {{ formatNumber(detailTransfer.discrepancy_quantity) }} {{ detailTransfer.unit }}</p>
                        <a v-if="detailTransfer.receiving_evidence_path" :href="`/storage/${detailTransfer.receiving_evidence_path}`" target="_blank" rel="noreferrer" class="mt-2 inline-flex items-center gap-1 text-xs font-bold text-teal-400"><FileText class="size-3.5" /> Mở bằng chứng</a>
                        <p v-if="detailTransfer.discrepancy_resolution" class="mt-2 text-xs text-muted-foreground"><b>Đã chốt:</b> {{ detailTransfer.discrepancy_resolution }}</p>
                    </div>
                </div>

                <div class="mt-5 flex flex-wrap justify-end gap-2">
                    <Button type="button" variant="outline" @click="closeModals">Đóng</Button>
                    <Button v-if="detailTransfer.can_route" type="button" class="gap-1.5 bg-indigo-600 font-bold text-white hover:bg-indigo-700" @click="openRoute(detailTransfer)"><RouteIcon class="size-3.5" /> Định tuyến</Button>
                    <Button v-if="detailTransfer.can_dispatch" type="button" class="gap-1.5 bg-amber-600 font-bold text-white hover:bg-amber-700" @click="openDispatch(detailTransfer)"><PackageOpen class="size-3.5" /> Xuất kho</Button>
                    <Button v-if="detailTransfer.can_receive" type="button" class="gap-1.5 bg-emerald-600 font-bold text-white hover:bg-emerald-700" @click="openReceive(detailTransfer)"><PackageCheck class="size-3.5" /> Nhận hàng</Button>
                    <Button v-if="detailTransfer.can_resolve" type="button" class="gap-1.5 bg-rose-600 font-bold text-white hover:bg-rose-700" @click="openResolve(detailTransfer)"><ClipboardCheck class="size-3.5" /> Chốt chênh lệch</Button>
                </div>
            </template>
            <template v-else-if="routing">
                <div class="mb-5 flex items-start justify-between gap-3">
                    <div>
                        <p
                            class="text-[10px] font-bold tracking-wider text-indigo-400 uppercase"
                        >
                            Bước 1 · Định tuyến
                        </p>
                        <h2 class="mt-1 text-xl font-black">
                            Chọn kho cấp hàng
                        </h2>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ routing.ingredient }} ·
                            {{ routing.quantity_requested }}
                            {{ routing.unit }} → {{ routing.to_branch }}
                        </p>
                    </div>
                    <Button variant="ghost" size="icon" @click="closeModals"
                        ><X class="size-4"
                    /></Button>
                </div>
                <form @submit.prevent="submitRoute" class="space-y-4">
                    <div class="flex flex-col gap-1.5">
                        <Label>Chi nhánh cấp hàng</Label
                        ><select
                            v-model="routeForm.from_branch_id"
                            required
                            class="h-10 rounded-md border border-input bg-background px-3 text-sm"
                        >
                            <option value="" disabled>
                                — Chọn nguồn có tồn —
                            </option>
                            <option
                                v-for="branch in props.branches.filter(
                                    (branch) =>
                                        branch.id !== routing?.to_branch_id,
                                )"
                                :key="branch.id"
                                :value="branch.id"
                            >
                                {{ branch.name }}
                            </option>
                        </select>
                        <p
                            v-if="routeForm.errors.from_branch_id"
                            class="text-xs text-rose-500"
                        >
                            {{ routeForm.errors.from_branch_id }}
                        </p>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <Label>Ghi chú điều phối</Label
                        ><textarea
                            v-model="routeForm.owner_note"
                            rows="3"
                            class="rounded-md border border-input bg-background px-3 py-2 text-sm"
                            placeholder="Ưu tiên lô gần hạn, ghi rõ cách bàn giao..."
                        />
                    </div>
                    <div class="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            @click="closeModals"
                            >Hủy</Button
                        ><Button
                            type="submit"
                            :disabled="routeForm.processing"
                            class="bg-indigo-600 font-bold text-white hover:bg-indigo-700"
                            >Định tuyến & sinh mã</Button
                        >
                    </div>
                </form>
            </template>

            <template v-else-if="dispatching">
                <div class="mb-5 flex items-start justify-between gap-3">
                    <div>
                        <p
                            class="text-[10px] font-bold tracking-wider text-amber-400 uppercase"
                        >
                            Bước 2 · Xuất kho
                        </p>
                        <h2 class="mt-1 text-xl font-black">
                            Xác nhận xuất hàng
                        </h2>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ dispatching.ingredient }} ·
                            {{ dispatching.from_branch }} →
                            {{ dispatching.to_branch }}
                        </p>
                    </div>
                    <Button variant="ghost" size="icon" @click="closeModals"
                        ><X class="size-4"
                    /></Button>
                </div>
                <div
                    class="mb-4 rounded-xl border border-amber-400/20 bg-amber-950/20 p-3 text-xs text-amber-100"
                >
                    <p>
                        Tồn hiện tại kho nguồn:
                        <b
                            >{{
                                formatNumber(
                                    dispatching.source_available_quantity,
                                )
                            }}
                            {{ dispatching.unit }}</b
                        >
                    </p>
                    <p class="mt-1 text-amber-100/70">
                        Hệ thống chỉ cho xuất đủ
                        {{ formatNumber(dispatching.quantity_requested) }}
                        {{ dispatching.unit }} để tránh tạo yêu cầu hoàn tất
                        giả.
                    </p>
                </div>
                <form @submit.prevent="submitDispatch" class="space-y-4">
                    <div class="flex flex-col gap-1.5">
                        <Label>Số lượng xuất</Label
                        ><Input
                            v-model="dispatchForm.quantity_dispatched"
                            type="number"
                            step="0.001"
                            min="0.001"
                            :max="dispatching.quantity_requested"
                            required
                        />
                        <p
                            v-if="dispatchForm.errors.quantity_dispatched"
                            class="text-xs text-rose-500"
                        >
                            {{ dispatchForm.errors.quantity_dispatched }}
                        </p>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <Label>Ghi chú xuất kho</Label
                        ><textarea
                            v-model="dispatchForm.dispatch_note"
                            rows="3"
                            class="rounded-md border border-input bg-background px-3 py-2 text-sm"
                            placeholder="Tình trạng đóng gói, người bàn giao, phương tiện..."
                        />
                    </div>
                    <div class="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            @click="closeModals"
                            >Hủy</Button
                        ><Button
                            type="submit"
                            :disabled="dispatchForm.processing"
                            class="bg-amber-600 font-bold text-white hover:bg-amber-700"
                            ><PackageOpen class="size-4" /> Xác nhận
                            xuất</Button
                        >
                    </div>
                </form>
            </template>

            <template v-else-if="receiving">
                <div class="mb-5 flex items-start justify-between gap-3">
                    <div>
                        <p
                            class="text-[10px] font-bold tracking-wider text-emerald-400 uppercase"
                        >
                            Bước 3 · Nhận hàng
                        </p>
                        <h2 class="mt-1 text-xl font-black">
                            Kiểm đếm thực nhận
                        </h2>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ receiving.ingredient }} · đã xuất
                            {{ receiving.quantity_dispatched }}
                            {{ receiving.unit }}
                        </p>
                    </div>
                    <Button variant="ghost" size="icon" @click="closeModals"
                        ><X class="size-4"
                    /></Button>
                </div>
                <form @submit.prevent="submitReceive" class="space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div class="flex flex-col gap-1.5">
                            <Label>Mã giao nhận</Label
                            ><Input
                                v-model="receiveForm.handover_code"
                                class="font-mono tracking-[0.2em] uppercase"
                                maxlength="6"
                                required
                                placeholder="ABC123"
                            />
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <Label>Số lượng thực nhận</Label
                            ><Input
                                v-model="receiveForm.quantity_received"
                                type="number"
                                step="0.001"
                                min="0"
                                :max="receiving.quantity_dispatched ?? 0"
                                required
                            />
                        </div>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <Label>Tình trạng hàng</Label
                        ><select
                            v-model="receiveForm.received_condition"
                            class="h-10 rounded-md border border-input bg-background px-3 text-sm"
                        >
                            <option value="good">Đủ và đạt chất lượng</option>
                            <option value="shortage">Thiếu số lượng</option>
                            <option value="damaged">Hư hỏng</option>
                            <option value="mixed">Vừa thiếu vừa hư hỏng</option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <Label>Biên bản nhận / mô tả chênh lệch</Label
                        ><textarea
                            v-model="receiveForm.received_note"
                            rows="4"
                            class="rounded-md border border-input bg-background px-3 py-2 text-sm"
                            placeholder="Bắt buộc ghi rõ nếu nhận thiếu hoặc hàng hỏng..."
                        />
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <Label
                            >Ảnh/PDF bằng chứng (bắt buộc nếu thiếu hoặc
                            hỏng)</Label
                        >
                        <Input
                            type="file"
                            accept="image/*,.pdf"
                            @change="onEvidenceChange"
                        />
                    </div>
                    <p
                        v-if="
                            receiveForm.errors.handover_code ||
                            receiveForm.errors.quantity_received
                        "
                        class="text-xs text-rose-500"
                    >
                        {{
                            receiveForm.errors.handover_code ||
                            receiveForm.errors.quantity_received
                        }}
                    </p>
                    <div class="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            @click="closeModals"
                            >Hủy</Button
                        ><Button
                            type="submit"
                            :disabled="receiveForm.processing"
                            class="bg-emerald-600 font-bold text-white hover:bg-emerald-700"
                            ><PackageCheck class="size-4" /> Xác nhận nhận
                            hàng</Button
                        >
                    </div>
                </form>
            </template>

            <template v-else-if="resolving">
                <div class="mb-5 flex items-start justify-between gap-3">
                    <div>
                        <p
                            class="text-[10px] font-bold tracking-wider text-rose-400 uppercase"
                        >
                            Đối soát
                        </p>
                        <h2 class="mt-1 text-xl font-black">Chốt chênh lệch</h2>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Thiếu {{ resolving.discrepancy_quantity }}
                            {{ resolving.unit }} · {{ resolving.ingredient }}
                        </p>
                    </div>
                    <Button variant="ghost" size="icon" @click="closeModals"
                        ><X class="size-4"
                    /></Button>
                </div>
                <form @submit.prevent="submitResolution" class="space-y-4">
                    <div
                        class="rounded-xl border border-rose-400/20 bg-rose-950/20 p-3 text-xs text-rose-100"
                    >
                        Hàng thực nhận đã được cộng vào kho đích. Việc chốt này
                        xác nhận hướng xử lý phần thiếu/hỏng và đóng hồ sơ điều
                        chuyển.
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <Label>Hướng xử lý / biên bản cuối</Label
                        ><textarea
                            v-model="resolutionForm.discrepancy_resolution"
                            rows="5"
                            required
                            class="rounded-md border border-input bg-background px-3 py-2 text-sm"
                            placeholder="Ví dụ: Đã xác nhận thiếu do hư hỏng trên đường, lập biên bản và ghi nhận chi phí hao hụt..."
                        />
                    </div>
                    <div class="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            @click="closeModals"
                            >Hủy</Button
                        ><Button
                            type="submit"
                            :disabled="resolutionForm.processing"
                            class="bg-rose-600 font-bold text-white hover:bg-rose-700"
                            ><ClipboardCheck class="size-4" /> Chốt hồ
                            sơ</Button
                        >
                    </div>
                </form>
            </template>

            <template v-else-if="cancelling || rejecting">
                <div class="mb-5 flex items-start justify-between gap-3">
                    <div>
                        <p
                            class="text-[10px] font-bold tracking-wider text-rose-400 uppercase"
                        >
                            Kiểm soát yêu cầu
                        </p>
                        <h2 class="mt-1 text-xl font-black">
                            {{ cancelling ? 'Hủy yêu cầu' : 'Từ chối yêu cầu' }}
                        </h2>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ (cancelling || rejecting)?.ingredient }} ·
                            {{ (cancelling || rejecting)?.to_branch }}
                        </p>
                    </div>
                    <Button variant="ghost" size="icon" @click="closeModals"
                        ><X class="size-4"
                    /></Button>
                </div>
                <form
                    v-if="cancelling"
                    @submit.prevent="submitCancel"
                    class="space-y-4"
                >
                    <div class="flex flex-col gap-1.5">
                        <Label>Lý do hủy</Label
                        ><textarea
                            v-model="cancelForm.cancel_reason"
                            rows="4"
                            required
                            class="rounded-md border border-input bg-background px-3 py-2 text-sm"
                            placeholder="Ví dụ: Chi nhánh đã tự cân đối được hàng..."
                        />
                    </div>
                    <div class="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            @click="closeModals"
                            >Quay lại</Button
                        ><Button
                            type="submit"
                            :disabled="cancelForm.processing"
                            class="bg-rose-600 font-bold text-white hover:bg-rose-700"
                            >Xác nhận hủy</Button
                        >
                    </div>
                </form>
                <form v-else @submit.prevent="submitReject" class="space-y-4">
                    <div class="flex flex-col gap-1.5">
                        <Label>Lý do từ chối</Label
                        ><textarea
                            v-model="rejectForm.reject_reason"
                            rows="4"
                            required
                            class="rounded-md border border-input bg-background px-3 py-2 text-sm"
                            placeholder="Ví dụ: Không có tồn khả dụng tại các kho cấp..."
                        />
                    </div>
                    <div class="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            @click="closeModals"
                            >Quay lại</Button
                        ><Button
                            type="submit"
                            :disabled="rejectForm.processing"
                            class="bg-rose-600 font-bold text-white hover:bg-rose-700"
                            >Từ chối yêu cầu</Button
                        >
                    </div>
                </form>
            </template>
        </div>
    </div>
    </Teleport>
</template>
