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
    Info,
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
import { computed, nextTick, ref, watch } from 'vue';
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
    | 'quarantined'
    | 'return_requested'
    | 'returned'
    | 'destroyed'
    | 'rejected'
    | 'cancelled';

interface Transfer {
    id: number;
    request_group_id: string | null;
    request_group_size?: number;
    status: TransferStatus;
    priority: 'normal' | 'urgent';
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
    quantity_received_good: number | null;
    quantity_received_damaged: number;
    quantity_received_expired: number;
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
    source_batch_id: number | null;
    destination_batch_id: number | null;
    quarantine_id: number | null;
    transport_temperature_min_c: number | null;
    transport_temperature_max_c: number | null;
    vehicle_number: string | null;
    carrier_name: string | null;
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

interface TransferGroup {
    key: string;
    request_group_id: string | null;
    is_group: boolean;
    to_branch_id: number;
    to_branch: string | null;
    requested_by: string | null;
    created_at: string;
    priority: 'normal' | 'urgent';
    reason: string;
    status: TransferStatus | 'mixed';
    items: Transfer[];
    can_batch_route: boolean;
    can_batch_dispatch: boolean;
    can_batch_receive: boolean;
    can_batch_reject: boolean;
    can_batch_cancel: boolean;
    has_overdue: boolean;
}

interface Branch {
    id: number;
    name: string;
}

type BranchStock = Record<string, Record<string, number>>;

interface IngredientOption {
    id: number;
    name: string;
    branch_id: number | null;
    unit: string;
}

const props = defineProps<{
    transfers: Transfer[];
    branches: Branch[];
    branch_stock: BranchStock;
    ingredients: IngredientOption[];
    permissions: {
        can_route: boolean;
        can_create: boolean;
        can_execute: boolean;
        request_only: boolean;
    };
    assigned_branch_id?: number | null;
    summary: {
        requested: number;
        routed: number;
        dispatched: number;
        discrepancy: number;
        completed: number;
    };
}>();

const requestOnly = computed(() => props.permissions.request_only === true);
const isBranchScoped = computed(
    () => Boolean(props.assigned_branch_id && !props.permissions.can_route),
);

const showRequest = ref(false);
const routing = ref<Transfer | null>(null);
const dispatching = ref<Transfer | null>(null);
const receiving = ref<Transfer | null>(null);
const resolving = ref<Transfer | null>(null);
const cancelling = ref<Transfer | null>(null);
const rejecting = ref<Transfer | null>(null);
const detailTransfer = ref<Transfer | null>(null);
const batchRoutingGroup = ref<TransferGroup | null>(null);
const batchRejectingGroup = ref<TransferGroup | null>(null);
const batchCancellingGroup = ref<TransferGroup | null>(null);
const statusFilter = ref<'all' | TransferStatus>('all');
const search = ref('');
const branchFilter = ref<number | 'all'>('all');
const workQueueOnly = ref(false);

interface RequestLine {
    ingredient_id: number | '';
    quantity_requested: number;
}

const createRequestLine = (): RequestLine => ({
    ingredient_id: '',
    quantity_requested: 0,
});

const requestForm = useForm<{
    to_branch_id: number | '';
    from_branch_id: number | '' | null;
    items: RequestLine[];
    priority: 'normal' | 'urgent';
    reason: string;
}>({
    to_branch_id: props.assigned_branch_id ?? props.branches[0]?.id ?? '',
    from_branch_id: null,
    items: [createRequestLine()],
    priority: requestOnly.value ? 'urgent' : 'normal',
    reason: '',
});

const availableIngredientsForLine = (lineIndex: number | string) =>
    props.ingredients.filter((ingredient) => {
        const belongsToBranch =
            ingredient.branch_id === null ||
            Number(ingredient.branch_id) === Number(requestForm.to_branch_id);
        const usedByAnotherLine = requestForm.items.some(
            (line: RequestLine, index: number) =>
                index !== Number(lineIndex) &&
                Number(line.ingredient_id) === Number(ingredient.id),
        );

        return belongsToBranch && !usedByAnotherLine;
    });

const selectedIngredientForLine = (lineIndex: number | string) =>
    props.ingredients.find(
        (ingredient) =>
            Number(ingredient.id) ===
            Number(requestForm.items[Number(lineIndex)]?.ingredient_id),
    );

const addRequestLine = () => {
    if (requestForm.items.length < 50) {
        requestForm.items.push(createRequestLine());
    }
};

const removeRequestLine = (lineIndex: number | string) => {
    if (requestForm.items.length > 1) {
        requestForm.items.splice(Number(lineIndex), 1);
    }
};

const routeForm = useForm({
    from_branch_id: '' as number | '',
    owner_note: '',
});

const batchRouteForm = useForm({
    request_group_id: '',
    transfer_ids: [] as number[],
    from_branch_id: '' as number | '',
    owner_note: '',
});

const batchRejectForm = useForm({
    request_group_id: '',
    transfer_ids: [] as number[],
    reject_reason: '',
});

const batchCancelForm = useForm({
    request_group_id: '',
    transfer_ids: [] as number[],
    cancel_reason: '',
});

const dispatchForm = useForm({
    quantity_dispatched: 0,
    dispatch_note: '',
});

const receiveForm = useForm({
    handover_code: '',
    quantity_received: 0,
    quantity_received_good: 0,
    quantity_received_damaged: 0,
    quantity_received_expired: 0,
    received_condition: 'good',
    received_note: '',
    transport_temperature_min_c: '' as number | string,
    transport_temperature_max_c: '' as number | string,
    vehicle_number: '',
    carrier_name: '',
    receiving_evidence: null as File | null,
});

const resolutionForm = useForm({ discrepancy_resolution: '' });
const cancelForm = useForm({ cancel_reason: '' });
const rejectForm = useForm({ reject_reason: '' });

// ── Bulk dispatch (Xuất hàng loạt) ────────────────────────────────
const showBulkDispatch = ref(false);
// step 1 = review table + evidence upload; step 2 = phiếu preview
const bulkDispatchStep = ref<1 | 2>(1);
const bulkDispatchEvidenceFile = ref<File | null>(null);
const bulkDispatchNote = ref('');
const bulkDispatchSubmitting = ref(false);
const bulkDispatchDone = ref(0); // how many have been dispatched so far

interface BulkLine {
    transfer: Transfer;
    qty: number; // quantity to dispatch
    maxQty: number;
    availableQty: number; // tồn kho tại thời điểm bấm
}
const bulkLines = ref<BulkLine[]>([]);

const dispatchableTransfers = computed(() =>
    props.transfers.filter((t) => t.can_dispatch),
);

const bulkTargetGroup = ref<TransferGroup | null>(null);

const openBulkDispatch = (group?: TransferGroup | null) => {
    bulkTargetGroup.value = group ?? null;

    const targetItems = group
        ? group.items.filter((t) => t.can_dispatch)
        : dispatchableTransfers.value;

    bulkLines.value = targetItems.map((t) => {
        const branchStock = t.from_branch_id
            ? getBranchStockForIngredient(t.from_branch_id, t.ingredient_id)
            : 0;
        const available =
            branchStock > 0
                ? branchStock
                : Number(t.source_available_quantity || 0);

        return {
            transfer: t,
            qty: getDispatchMaxAllowed(t),
            maxQty: getDispatchMaxAllowed(t),
            availableQty: available,
        };
    });
    bulkDispatchEvidenceFile.value = null;
    bulkDispatchNote.value = '';
    bulkDispatchStep.value = 1;
    bulkDispatchDone.value = 0;
    showBulkDispatch.value = true;
};

const closeBulkDispatch = () => {
    showBulkDispatch.value = false;
};

const onBulkEvidenceChange = (e: Event) => {
    const input = e.target as HTMLInputElement;
    bulkDispatchEvidenceFile.value = input.files?.[0] ?? null;
};

const goToBulkPreview = () => {
    bulkDispatchStep.value = 2;
};

const goBackToBulkForm = () => {
    bulkDispatchStep.value = 1;
};

const submitBulkDispatch = async () => {
    if (bulkDispatchSubmitting.value) {
        return;
    }

    bulkDispatchSubmitting.value = true;

    bulkDispatchDone.value = 0;

    const lines = bulkLines.value.filter((l) => l.qty > 0);

    for (const line of lines) {
        await new Promise<void>((resolve) => {
            const fd = new FormData();

            fd.append('quantity_dispatched', String(line.qty));

            fd.append('dispatch_note', bulkDispatchNote.value);

            if (bulkDispatchEvidenceFile.value) {
                fd.append('dispatch_evidence', bulkDispatchEvidenceFile.value);
            }

            router.post(
                `/inventory/transfers/${line.transfer.id}/dispatch`,
                fd,
                {
                    preserveScroll: true,
                    onFinish: () => {
                        bulkDispatchDone.value += 1;
                        resolve();
                    },
                },
            );
        });
    }

    bulkDispatchSubmitting.value = false;
    closeBulkDispatch();
    refreshPage();
};

const bulkTotalCost = computed(() =>
    bulkLines.value.reduce(
        (sum, line) =>
            sum + line.qty * (line.transfer.source_unit_cost ?? 0),
        0,
    ),
);

const bulkToday = computed(() => {
    const now = new Date();

    return {
        day: String(now.getDate()).padStart(2, '0'),
        month: String(now.getMonth() + 1).padStart(2, '0'),
        year: String(now.getFullYear()),
        time: now.toLocaleTimeString('vi-VN', {
            hour: '2-digit',
            minute: '2-digit',
        }),
    };
});

// ── Bulk Receive (Nhận hàng hàng loạt) ─────────────────────────────
const showBulkReceive = ref(false);
const bulkReceiveStep = ref<1 | 2>(1);
const bulkReceiveEvidenceFile = ref<File | null>(null);
const bulkReceiveNote = ref('');
const bulkReceiveHandoverCode = ref('');
const bulkReceiveSubmitting = ref(false);
const bulkReceiveDone = ref(0);

interface BulkReceiveLine {
    transfer: Transfer;
    dispatchedQty: number;
    goodQty: number; // SL đạt chuẩn
    damagedQty: number; // SL hỏng / lỗi / hết hạn (cách ly)
    shortageQty: number; // SL thiếu
    condition: 'good' | 'damaged' | 'shortage' | 'mixed';
}
const bulkReceiveLines = ref<BulkReceiveLine[]>([]);
const bulkReceiveTargetGroup = ref<TransferGroup | null>(null);

const receivableTransfers = computed(() =>
    props.transfers.filter((t) => t.can_receive),
);

const openBulkReceive = (group?: TransferGroup | null) => {
    bulkReceiveTargetGroup.value = group ?? null;

    const targetItems = group
        ? group.items.filter((t) => t.can_receive)
        : receivableTransfers.value;

    bulkReceiveHandoverCode.value =
        targetItems[0]?.handover_code ||
        'GN-' + Math.random().toString(36).substring(2, 8).toUpperCase();

    bulkReceiveLines.value = targetItems.map((t) => {
        const dispatched = Number(
            t.quantity_dispatched ?? t.quantity_requested ?? 0,
        );

        return {
            transfer: t,
            dispatchedQty: dispatched,
            goodQty: dispatched,
            damagedQty: 0,
            shortageQty: 0,
            condition: 'good' as const,
        };
    });

    bulkReceiveEvidenceFile.value = null;
    bulkReceiveNote.value = '';
    bulkReceiveStep.value = 1;
    bulkReceiveDone.value = 0;
    showBulkReceive.value = true;
};

const closeBulkReceive = () => {
    showBulkReceive.value = false;
};

const onBulkReceiveEvidenceChange = (e: Event) => {
    const input = e.target as HTMLInputElement;

    bulkReceiveEvidenceFile.value = input.files?.[0] ?? null;
};

const updateReceiveLineCondition = (line: BulkReceiveLine) => {
    const damaged = Number(line.damagedQty) || 0;
    const shortage = Number(line.shortageQty) || 0;

    if (damaged > 0 && shortage > 0) {
        line.condition = 'mixed';
    } else if (damaged > 0) {
        line.condition = 'damaged';
    } else if (shortage > 0) {
        line.condition = 'shortage';
    } else {
        line.condition = 'good';
    }
};

const updateReceiveLineFromGood = (line: BulkReceiveLine) => {
    let good = Number(line.goodQty);

    if (isNaN(good) || good < 0) {
        good = 0;
    }

    if (good > line.dispatchedQty) {
        good = line.dispatchedQty;
    }

    line.goodQty = good;

    const remaining = Number((line.dispatchedQty - good).toFixed(3));
    let damaged = Number(line.damagedQty) || 0;

    if (damaged > remaining) {
        damaged = remaining;
        line.damagedQty = damaged;
    }

    line.shortageQty = Math.max(0, Number((remaining - damaged).toFixed(3)));
    updateReceiveLineCondition(line);
};

const updateReceiveLineFromDamaged = (line: BulkReceiveLine) => {
    let damaged = Number(line.damagedQty);

    if (isNaN(damaged) || damaged < 0) {
        damaged = 0;
    }

    if (damaged > line.dispatchedQty) {
        damaged = line.dispatchedQty;
    }

    line.damagedQty = damaged;

    let good = Number(line.goodQty) || 0;

    if (good + damaged > line.dispatchedQty) {
        good = Math.max(0, Number((line.dispatchedQty - damaged).toFixed(3)));
        line.goodQty = good;
    }

    line.shortageQty = Math.max(0, Number((line.dispatchedQty - good - damaged).toFixed(3)));
    updateReceiveLineCondition(line);
};

const updateReceiveLineFromShortage = (line: BulkReceiveLine) => {
    let shortage = Number(line.shortageQty);

    if (isNaN(shortage) || shortage < 0) {
        shortage = 0;
    }

    if (shortage > line.dispatchedQty) {
        shortage = line.dispatchedQty;
    }

    line.shortageQty = shortage;

    let damaged = Number(line.damagedQty) || 0;

    if (damaged + shortage > line.dispatchedQty) {
        damaged = Math.max(0, Number((line.dispatchedQty - shortage).toFixed(3)));
        line.damagedQty = damaged;
    }

    line.goodQty = Math.max(0, Number((line.dispatchedQty - damaged - shortage).toFixed(3)));
    updateReceiveLineCondition(line);
};

const getLineSum = (line: BulkReceiveLine) => {
    return Number(
        (
            (Number(line.goodQty) || 0) +
            (Number(line.damagedQty) || 0) +
            (Number(line.shortageQty) || 0)
        ).toFixed(3),
    );
};

const isLineBalanced = (line: BulkReceiveLine) => {
    return Math.abs(getLineSum(line) - line.dispatchedQty) < 0.0005;
};

const bulkReceiveIsAllBalanced = computed(() =>
    bulkReceiveLines.value.every((l) => isLineBalanced(l)),
);

const canGoToBulkReceivePreview = computed(() => {
    const isBalanced = bulkReceiveIsAllBalanced.value;
    const hasNote = Boolean(
        bulkReceiveNote.value && bulkReceiveNote.value.trim().length > 0,
    );
    const hasEvidence = Boolean(bulkReceiveEvidenceFile.value);

    return isBalanced && hasNote && hasEvidence;
});

const goToBulkReceivePreview = () => {
    bulkReceiveStep.value = 2;
};

const goBackToBulkReceiveForm = () => {
    bulkReceiveStep.value = 1;
};

const submitBulkReceive = async () => {
    if (bulkReceiveSubmitting.value) {
        return;
    }

    bulkReceiveSubmitting.value = true;
    bulkReceiveDone.value = 0;

    for (const line of bulkReceiveLines.value) {
        await new Promise<void>((resolve) => {
            const fd = new FormData();
            const good = Number(line.goodQty) || 0;
            const damaged = Number(line.damagedQty) || 0;
            const totalRec = good + damaged;

            fd.append(
                'handover_code',
                bulkReceiveHandoverCode.value || line.transfer.handover_code || '',
            );
            fd.append('quantity_received', String(totalRec));
            fd.append('quantity_received_good', String(good));
            fd.append('quantity_received_damaged', String(damaged));
            fd.append('quantity_received_expired', '0');
            fd.append('received_condition', line.condition);
            fd.append(
                'received_note',
                bulkReceiveNote.value || 'Nhận hàng đồng bộ theo phiếu',
            );

            if (bulkReceiveEvidenceFile.value) {
                fd.append('receiving_evidence', bulkReceiveEvidenceFile.value);
            }

            router.post(
                `/inventory/transfers/${line.transfer.id}/receive`,
                fd,
                {
                    preserveScroll: true,
                    onFinish: () => {
                        bulkReceiveDone.value += 1;
                        resolve();
                    },
                },
            );
        });
    }

    bulkReceiveSubmitting.value = false;
    closeBulkReceive();
    refreshPage();
};

const bulkReceiveTotalCost = computed(() =>
    bulkReceiveLines.value.reduce(
        (sum, line) =>
            sum +
            (Number(line.goodQty) || 0) *
                (line.transfer.source_unit_cost ?? 0),
        0,
    ),
);

const bulkReceiveHasDiscrepancy = computed(() =>
    bulkReceiveLines.value.some(
        (l) => l.damagedQty > 0 || l.shortageQty > 0,
    ),
);

const routeBranchOptions = computed(() => {
    const transfer = routing.value;

    if (!transfer) {
        return [];
    }

    return props.branches
        .filter((branch) => branch.id !== transfer.to_branch_id)
        .map((branch) => ({
            ...branch,
            available_quantity:
                props.branch_stock[String(branch.id)]?.[
                    String(transfer.ingredient_id)
                ] ?? 0,
        }));
});

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
    Math.max(
        0,
        Math.round(ageInHours(transfer) - (slaHours[transfer.status] ?? 0)),
    );

const needsAction = (transfer: Transfer) =>
    transfer.can_route ||
    transfer.can_dispatch ||
    transfer.can_receive ||
    transfer.can_resolve;

const shouldShowInQueue = (transfer: Transfer) =>
    needsAction(transfer) ||
    (requestOnly.value &&
        ['requested', 'routed', 'dispatched', 'discrepancy'].includes(
            transfer.status,
        ));

const nextAction = (transfer: Transfer) => {
    if (requestOnly.value && transfer.status === 'requested') {
        return 'Chờ Chủ doanh nghiệp xem xét';
    }

    if (transfer.can_route) {
        return 'Định tuyến nguồn cấp';
    }

    if (transfer.can_dispatch) {
        return 'Xác nhận xuất kho';
    }

    if (transfer.can_receive) {
        return 'Kiểm đếm & nhận hàng';
    }

    if (transfer.can_resolve) {
        return 'Chốt chênh lệch';
    }

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
            transfer.discrepancy_quantity * (transfer.source_unit_cost ?? 0),
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

const groupedWorkQueue = computed<TransferGroup[]>(() => {
    const queueTransfers = props.transfers.filter(shouldShowInQueue);
    const groupsMap = new Map<string, TransferGroup>();

    queueTransfers.forEach((transfer) => {
        const groupKey = transfer.request_group_id
            ? `grp_${transfer.request_group_id}`
            : `single_${transfer.id}`;

        if (!groupsMap.has(groupKey)) {
            groupsMap.set(groupKey, {
                key: groupKey,
                request_group_id: transfer.request_group_id || null,
                is_group: false,
                to_branch_id: transfer.to_branch_id,
                to_branch: transfer.to_branch,
                requested_by: transfer.requested_by,
                created_at: transfer.created_at,
                priority: transfer.priority,
                reason: transfer.reason,
                status: transfer.status,
                items: [],
                can_batch_route: false,
                can_batch_dispatch: false,
                can_batch_receive: false,
                can_batch_reject: false,
                can_batch_cancel: false,
                has_overdue: false,
            });
        }

        const grp = groupsMap.get(groupKey)!;
        grp.items.push(transfer);

        if (grp.priority !== 'urgent' && transfer.priority === 'urgent') {
            grp.priority = 'urgent';
        }

        if (isOverdue(transfer)) {
            grp.has_overdue = true;
        }
    });

    return Array.from(groupsMap.values())
        .map((grp) => {
            grp.is_group = grp.items.length > 1;
            const allRequested = grp.items.every(
                (t) => t.status === 'requested',
            );
            const anyCanRoute = grp.items.some((t) => t.can_route);
            const anyCanCancel = grp.items.some((t) => t.can_cancel);

            grp.can_batch_route = anyCanRoute && allRequested;
            grp.can_batch_dispatch = grp.items.some((t) => t.can_dispatch);
            grp.can_batch_receive = grp.items.some((t) => t.can_receive);
            grp.can_batch_reject =
                anyCanRoute &&
                grp.items.some((t) =>
                    ['requested', 'routed'].includes(t.status),
                );
            grp.can_batch_cancel =
                anyCanCancel &&
                grp.items.some((t) =>
                    ['requested', 'routed'].includes(t.status),
                );

            const statuses = new Set(grp.items.map((t) => t.status));
            grp.status = statuses.size === 1 ? grp.items[0].status : 'mixed';

            return grp;
        })
        .sort((a, b) => {
            const overdueDiff = Number(b.has_overdue) - Number(a.has_overdue);
            const urgentDiff =
                Number(b.priority === 'urgent') -
                Number(a.priority === 'urgent');

            if (overdueDiff !== 0) {
                return overdueDiff;
            }

            if (urgentDiff !== 0) {
                return urgentDiff;
            }

            const maxIdA = Math.max(...a.items.map((t) => t.id), 0);
            const maxIdB = Math.max(...b.items.map((t) => t.id), 0);

            return maxIdB - maxIdA;
        })
        .slice(0, 6);
});

const nextActionForGroup = (group: TransferGroup) => {
    if (requestOnly.value && group.status === 'requested') {
        return 'Chờ Chủ doanh nghiệp xem xét';
    }

    if (group.can_batch_route) {
        return 'Định tuyến cả phiếu';
    }

    if (group.items.some((t) => t.can_dispatch)) {
        return 'Xác nhận xuất kho';
    }

    if (group.items.some((t) => t.can_receive)) {
        return 'Kiểm đếm & nhận hàng';
    }

    if (group.items.some((t) => t.can_resolve)) {
        return 'Chốt chênh lệch';
    }

    return 'Theo dõi tiến độ';
};

const handleWorkQueueGroupClick = (group: TransferGroup) => {
    if (group.is_group) {
        if (group.can_batch_route) {
            openBatchRoute(group);
        } else if (group.can_batch_dispatch) {
            openBulkDispatch(group);
        } else {
            openDetails(group.items[0]);
        }
    } else {
        openDetails(group.items[0]);
    }
};

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

const actionFilter = ref<'all' | 'dispatch' | 'receive'>('all');

const filterAndScrollTo = (action: 'dispatch' | 'receive') => {
    if (actionFilter.value === action) {
        actionFilter.value = 'all';

        return;
    }

    actionFilter.value = action;
    statusFilter.value = 'all';
    workQueueOnly.value = false;

    nextTick(() => {
        const target =
            document.getElementById('transfers-filter-banner') ||
            document.getElementById('transfers-list-section');

        if (target) {
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });
};

const clearActionFilter = () => {
    actionFilter.value = 'all';
};

const filteredTransfers = computed(() => {
    const query = search.value.trim().toLowerCase();

    return props.transfers.filter((transfer) => {
        if (actionFilter.value === 'dispatch' && !transfer.can_dispatch) {
            return false;
        }

        if (actionFilter.value === 'receive' && !transfer.can_receive) {
            return false;
        }

        const matchesStatus =
            statusFilter.value === 'all' ||
            transfer.status === statusFilter.value;
        const matchesBranch =
            branchFilter.value === 'all' ||
            transfer.from_branch_id === branchFilter.value ||
            transfer.to_branch_id === branchFilter.value;
        const matchesQueue =
            !workQueueOnly.value || shouldShowInQueue(transfer);
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

const groupedTransfers = computed<TransferGroup[]>(() => {
    const groupsMap = new Map<string, TransferGroup>();

    filteredTransfers.value.forEach((transfer) => {
        const groupKey = transfer.request_group_id
            ? `grp_${transfer.request_group_id}`
            : `single_${transfer.id}`;

        if (!groupsMap.has(groupKey)) {
            groupsMap.set(groupKey, {
                key: groupKey,
                request_group_id: transfer.request_group_id || null,
                is_group: false,
                to_branch_id: transfer.to_branch_id,
                to_branch: transfer.to_branch,
                requested_by: transfer.requested_by,
                created_at: transfer.created_at,
                priority: transfer.priority,
                reason: transfer.reason,
                status: transfer.status,
                items: [],
                can_batch_route: false,
                can_batch_dispatch: false,
                can_batch_receive: false,
                can_batch_reject: false,
                can_batch_cancel: false,
                has_overdue: false,
            });
        }

        const grp = groupsMap.get(groupKey)!;
        grp.items.push(transfer);

        if (grp.priority !== 'urgent' && transfer.priority === 'urgent') {
            grp.priority = 'urgent';
        }

        if (isOverdue(transfer)) {
            grp.has_overdue = true;
        }
    });

    return Array.from(groupsMap.values()).map((grp) => {
        grp.is_group = grp.items.length > 1;
        const allRequested = grp.items.every((t) => t.status === 'requested');
        const anyCanRoute = grp.items.some((t) => t.can_route);
        const anyCanCancel = grp.items.some((t) => t.can_cancel);

        grp.can_batch_route = anyCanRoute && allRequested;
        grp.can_batch_dispatch = grp.items.some((t) => t.can_dispatch);
        grp.can_batch_receive = grp.items.some((t) => t.can_receive);
        grp.can_batch_reject =
            anyCanRoute &&
            grp.items.some((t) => ['requested', 'routed'].includes(t.status));
        grp.can_batch_cancel =
            anyCanCancel &&
            grp.items.some((t) => ['requested', 'routed'].includes(t.status));

        const statuses = new Set(grp.items.map((t) => t.status));
        grp.status = statuses.size === 1 ? grp.items[0].status : 'mixed';

        return grp;
    });
});

const batchRouteBranchOptions = computed(() => {
    const group = batchRoutingGroup.value;

    if (!group) {
        return [];
    }

    return props.branches.filter((branch) => branch.id !== group.to_branch_id);
});

const getBranchStockForIngredient = (
    branchId: number | '',
    ingredientId: number,
): number => {
    if (!branchId) {
        return 0;
    }

    return props.branch_stock[String(branchId)]?.[String(ingredientId)] ?? 0;
};

const hasShortageInBatchRoute = computed(() => {
    const group = batchRoutingGroup.value;

    if (!group || !batchRouteForm.from_branch_id) {
        return false;
    }

    return group.items.some((item) => {
        const available = getBranchStockForIngredient(
            batchRouteForm.from_branch_id,
            item.ingredient_id,
        );

        return available + 0.0005 < item.quantity_requested;
    });
});

const openBatchRoute = (group: TransferGroup) => {
    detailTransfer.value = null;
    batchRoutingGroup.value = group;
    batchRouteForm.request_group_id = group.request_group_id ?? '';
    batchRouteForm.transfer_ids = group.items.map((t) => t.id);
    batchRouteForm.from_branch_id = '';
    batchRouteForm.owner_note = '';
};

const openBatchReject = (group: TransferGroup) => {
    detailTransfer.value = null;
    batchRejectingGroup.value = group;
    batchRejectForm.request_group_id = group.request_group_id ?? '';
    batchRejectForm.transfer_ids = group.items.map((t) => t.id);
    batchRejectForm.reject_reason = '';
};

const openBatchCancel = (group: TransferGroup) => {
    detailTransfer.value = null;
    batchCancellingGroup.value = group;
    batchCancelForm.request_group_id = group.request_group_id ?? '';
    batchCancelForm.transfer_ids = group.items.map((t) => t.id);
    batchCancelForm.cancel_reason = '';
};

const submitBatchRoute = () => {
    if (!batchRoutingGroup.value || batchRouteForm.processing) {
        return;
    }

    batchRouteForm.post('/inventory/transfers/batch-route', {
        preserveScroll: true,
        onSuccess: closeModals,
    });
};

const submitBatchReject = () => {
    if (!batchRejectingGroup.value || batchRejectForm.processing) {
        return;
    }

    batchRejectForm.post('/inventory/transfers/batch-reject', {
        preserveScroll: true,
        onSuccess: closeModals,
    });
};

const submitBatchCancel = () => {
    if (!batchCancellingGroup.value || batchCancelForm.processing) {
        return;
    }

    batchCancelForm.post('/inventory/transfers/batch-cancel', {
        preserveScroll: true,
        onSuccess: closeModals,
    });
};

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
    quarantined: {
        label: 'Cách ly',
        className: 'border-orange-400/30 bg-orange-500/10 text-orange-300',
        icon: PackageOpen,
    },
    return_requested: {
        label: 'Yêu cầu hoàn trả',
        className: 'border-fuchsia-400/30 bg-fuchsia-500/10 text-fuchsia-300',
        icon: RefreshCw,
    },
    returned: {
        label: 'Đã hoàn trả',
        className: 'border-cyan-400/30 bg-cyan-500/10 text-cyan-300',
        icon: RefreshCw,
    },
    destroyed: {
        label: 'Đã tiêu hủy',
        className: 'border-slate-400/30 bg-slate-500/10 text-slate-400',
        icon: XCircle,
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

const statusLabel = (status: TransferStatus) =>
    requestOnly.value && status === 'requested'
        ? 'Chờ Chủ doanh nghiệp xem xét'
        : statusConfig[status].label;

const openRoute = (transfer: Transfer) => {
    detailTransfer.value = null;
    routing.value = transfer;
    routeForm.reset();
};

const getDispatchMaxAllowed = (transfer: Transfer | null) => {
    if (!transfer) {
        return 0;
    }

    const available = Number(transfer.source_available_quantity || 0);
    const requested = Number(transfer.quantity_requested || 0);

    if (available >= requested) {
        return requested;
    }

    return Math.max(0.001, Number((available * (2 / 3)).toFixed(3)));
};

const openDispatch = (transfer: Transfer) => {
    detailTransfer.value = null;
    openBulkDispatch({
        key: `single_${transfer.id}`,
        request_group_id: transfer.request_group_id,
        is_group: false,
        to_branch_id: transfer.to_branch_id,
        to_branch: transfer.to_branch,
        requested_by: transfer.requested_by,
        created_at: transfer.created_at,
        priority: transfer.priority,
        reason: transfer.reason,
        status: transfer.status,
        items: [transfer],
        can_batch_route: false,
        can_batch_dispatch: true,
        can_batch_receive: false,
        can_batch_reject: false,
        can_batch_cancel: false,
        has_overdue: false,
    });
};

const openReceive = (transfer: Transfer) => {
    detailTransfer.value = null;
    openBulkReceive({
        key: `single_${transfer.id}`,
        request_group_id: transfer.request_group_id,
        is_group: false,
        to_branch_id: transfer.to_branch_id,
        to_branch: transfer.to_branch,
        requested_by: transfer.requested_by,
        created_at: transfer.created_at,
        priority: transfer.priority,
        reason: transfer.reason,
        status: transfer.status,
        items: [transfer],
        can_batch_route: false,
        can_batch_dispatch: false,
        can_batch_receive: true,
        can_batch_reject: false,
        can_batch_cancel: false,
        has_overdue: false,
    });
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

const openCreateRequest = () => {
    requestForm.reset();
    requestForm.items = [createRequestLine()];
    requestForm.from_branch_id = null;
    requestForm.priority = requestOnly.value ? 'urgent' : 'normal';

    if (props.assigned_branch_id) {
        requestForm.to_branch_id = props.assigned_branch_id;
    } else if (props.branches.length > 0 && !requestForm.to_branch_id) {
        requestForm.to_branch_id = props.branches[0].id;
    }

    showRequest.value = true;
};

const closeModals = () => {
    showRequest.value = false;
    routing.value = null;
    dispatching.value = null;
    receiving.value = null;
    resolving.value = null;
    cancelling.value = null;
    rejecting.value = null;
    detailTransfer.value = null;
    batchRoutingGroup.value = null;
    batchRejectingGroup.value = null;
    batchCancellingGroup.value = null;
};

const openDetails = (transfer: Transfer) => {
    detailTransfer.value = transfer;
};

const setStatusFilter = (status: 'all' | TransferStatus) => {
    statusFilter.value = status;
    workQueueOnly.value = false;
    actionFilter.value = 'all';
};

const setWorkQueue = () => {
    workQueueOnly.value = true;
    statusFilter.value = 'all';
    actionFilter.value = 'all';
};

const refreshPage = () => {
    router.reload({ preserveScroll: true });
};

watch(
    () => requestForm.to_branch_id,
    () => {
        requestForm.items.forEach((line: RequestLine) => {
            const ingredient = props.ingredients.find(
                (option) => Number(option.id) === Number(line.ingredient_id),
            );

            if (
                ingredient &&
                ingredient.branch_id !== null &&
                Number(ingredient.branch_id) !== Number(requestForm.to_branch_id)
            ) {
                line.ingredient_id = '';
            }
        });
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

    receiveForm.quantity_received =
        Number(receiveForm.quantity_received_good || 0) +
        Number(receiveForm.quantity_received_damaged || 0) +
        Number(receiveForm.quantity_received_expired || 0);
    receiveForm.received_condition =
        Number(receiveForm.quantity_received_damaged || 0) +
            Number(receiveForm.quantity_received_expired || 0) >
        0
            ? 'damaged'
            : receiveForm.quantity_received <
                Number(receiving.value.quantity_dispatched ?? 0)
              ? 'shortage'
              : 'good';
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
    if (!hours) {
        return '—';
    }

    if (hours < 24) {
        return `${Math.round(hours)} giờ`;
    }

    return `${Math.floor(hours / 24)} ngày ${Math.round(hours % 24)} giờ`;
};
</script>

<template>
    <Head :title="requestOnly ? 'Xin điều chuyển kho' : 'Điều chuyển kho'" />

    <div class="w-full space-y-6 p-4 sm:p-6 lg:p-8">
        <!-- Modern Unified Header -->
        <div
            class="flex flex-col gap-4 rounded-3xl border border-border/80 bg-card p-5 shadow-xs sm:p-6 md:flex-row md:items-center md:justify-between"
        >
            <div class="flex items-center gap-4">
                <div
                    class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-teal-600 text-white shadow-sm shadow-teal-600/20"
                >
                    <ArrowLeftRight class="size-6" />
                </div>
                <div>
                    <h1
                        class="text-xl font-bold tracking-tight text-foreground sm:text-2xl"
                    >
                        {{
                            requestOnly
                                ? 'Xin điều chuyển kho'
                                : 'Điều chuyển kho nội bộ'
                        }}
                    </h1>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        <template v-if="requestOnly">
                            Gửi yêu cầu bổ sung nguyên liệu đột xuất để Chủ doanh nghiệp xem xét và điều phối
                        </template>
                        <template v-else>
                            Theo dõi chu trình định tuyến nguồn, xuất kho, vận chuyển và đối soát chênh lệch
                        </template>
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <!-- Bulk dispatch button: only for warehouse/owner who can_execute and has items to dispatch -->
                <Button
                    v-if="props.permissions.can_execute && dispatchableTransfers.length > 0"
                    @click="filterAndScrollTo('dispatch')"
                    class="gap-2 rounded-xl bg-amber-500 font-semibold text-white shadow-sm shadow-amber-500/25 transition-all duration-200 hover:bg-amber-600 active:scale-95"
                    :class="
                        actionFilter === 'dispatch'
                            ? 'ring-2 ring-white shadow-lg shadow-amber-500/50 scale-[1.03]'
                            : ''
                    "
                    id="bulk-dispatch-btn"
                    title="Nhấn để lọc các phiếu cần xuất nguyên liệu và kéo xuống danh sách"
                >
                    <PackageOpen class="size-4" /> Xuất nguyên liệu
                    <span class="inline-flex size-5 items-center justify-center rounded-full bg-white/25 text-[11px] font-black">{{ dispatchableTransfers.length }}</span>
                </Button>
                <!-- Bulk receive button: only for warehouse/owner who can_execute and has items to receive -->
                <Button
                    v-if="props.permissions.can_execute && receivableTransfers.length > 0"
                    @click="filterAndScrollTo('receive')"
                    class="gap-2 rounded-xl bg-emerald-600 font-semibold text-white shadow-sm shadow-emerald-600/25 transition-all duration-200 hover:bg-emerald-700 active:scale-95"
                    :class="
                        actionFilter === 'receive'
                            ? 'ring-2 ring-white shadow-lg shadow-emerald-600/50 scale-[1.03]'
                            : ''
                    "
                    id="bulk-receive-btn"
                    title="Nhấn để lọc các phiếu cần nhận nguyên liệu và kéo xuống danh sách"
                >
                    <PackageCheck class="size-4" /> Nhận nguyên liệu
                    <span class="inline-flex size-5 items-center justify-center rounded-full bg-white/25 text-[11px] font-black">{{ receivableTransfers.length }}</span>
                </Button>
                <Button
                    v-if="props.permissions.can_create"
                    @click="openCreateRequest"
                    class="gap-2 rounded-xl bg-teal-600 font-semibold text-white shadow-sm hover:bg-teal-700"
                >
                    <Plus class="size-4" /> Tạo yêu cầu
                </Button>
            </div>
        </div>

        <section class="grid grid-cols-2 gap-3 lg:grid-cols-5">
            <button
                type="button"
                @click="setStatusFilter('requested')"
                class="rounded-2xl border border-blue-400/15 bg-blue-950/15 p-4"
            >
                <p
                    class="text-[10px] font-bold tracking-wider text-blue-300 uppercase"
                >
                    {{
                        requestOnly
                            ? 'Chờ Chủ doanh nghiệp xem xét'
                            : 'Chờ định tuyến'
                    }}
                </p>
                <p class="mt-2 text-2xl font-black text-white">
                    {{ props.summary.requested }}
                </p>
                <p class="mt-1 text-[11px] text-muted-foreground">
                    {{
                        requestOnly ? 'Chưa được điều phối' : 'Cần chọn kho cấp'
                    }}
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
            <div
                class="rounded-2xl border border-border bg-card/70 p-4 shadow-sm sm:p-5"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <ListTodo class="size-4 text-teal-400" />
                            <h2 class="font-black text-foreground">
                                {{
                                    requestOnly
                                        ? 'Yêu cầu đang theo dõi'
                                        : 'Việc cần xử lý'
                                }}
                            </h2>
                        </div>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{
                                requestOnly
                                    ? 'Theo dõi yêu cầu bổ sung và phản hồi điều phối từ Chủ doanh nghiệp.'
                                    : 'Ưu tiên các phiếu đang chờ người dùng hiện tại thao tác.'
                            }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-teal-400/20 px-2.5 py-1.5 text-xs font-bold text-teal-300 transition hover:bg-teal-400/10"
                        @click="setWorkQueue"
                    >
                        <Filter class="size-3.5" />
                        {{
                            requestOnly
                                ? 'Xem toàn bộ yêu cầu'
                                : 'Xem toàn bộ hàng đợi'
                        }}
                    </button>
                </div>

                <div v-if="groupedWorkQueue.length" class="mt-4 space-y-2">
                    <template
                        v-for="group in groupedWorkQueue"
                        :key="group.key"
                    >
                        <!-- Group Requisition in Work Queue -->
                        <div
                            v-if="group.is_group"
                            class="flex flex-col gap-3 rounded-xl border border-indigo-100 bg-indigo-50/70 p-3 sm:flex-row sm:items-center sm:justify-between dark:border-indigo-500/30 dark:bg-indigo-950/20"
                        >
                            <button
                                type="button"
                                class="min-w-0 flex-1 text-left"
                                @click="handleWorkQueueGroupClick(group)"
                            >
                                <div class="flex flex-wrap items-center gap-2">
                                    <span
                                        class="rounded bg-indigo-100 px-1.5 py-0.5 font-mono text-[10px] font-bold text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300"
                                    >
                                        #{{
                                            group.request_group_id
                                                ? group.request_group_id
                                                      .slice(0, 8)
                                                      .toUpperCase()
                                                : group.key
                                        }}
                                    </span>
                                    <span
                                        class="truncate text-sm font-black text-foreground"
                                    >
                                        Đơn điều chuyển ({{
                                            group.items.length
                                        }}
                                        nguyên liệu)
                                    </span>
                                    <span
                                        v-if="group.priority === 'urgent'"
                                        class="inline-flex items-center gap-1 rounded-full border border-orange-400/30 bg-orange-500/10 px-2 py-0.5 text-[10px] font-bold text-orange-300"
                                    >
                                        <AlertTriangle class="size-3" />
                                        Khẩn cấp
                                    </span>
                                    <span
                                        v-if="group.has_overdue"
                                        class="inline-flex items-center gap-1 rounded-full border border-rose-400/30 bg-rose-500/10 px-2 py-0.5 text-[10px] font-bold text-rose-300"
                                    >
                                        <Timer class="size-3" /> Quá SLA
                                    </span>
                                </div>
                                <p
                                    class="mt-1 truncate text-xs text-muted-foreground"
                                >
                                    {{
                                        group.items[0]?.from_branch ||
                                        'Chưa chọn nguồn'
                                    }}
                                    → {{ group.to_branch }} ·
                                    <span
                                        class="font-semibold text-indigo-300"
                                        >{{ nextActionForGroup(group) }}</span
                                    >
                                    ·
                                    <span class="text-muted-foreground/80">{{
                                        group.items
                                            .map((i) => i.ingredient)
                                            .join(', ')
                                    }}</span>
                                </p>
                            </button>
                            <div class="flex shrink-0 items-center gap-2">
                                <span
                                    class="rounded-full bg-indigo-500/15 px-2.5 py-0.5 text-xs font-bold text-indigo-300"
                                >
                                    {{ group.items.length }} món
                                </span>
                                <Button
                                    size="sm"
                                    class="gap-1.5 bg-indigo-600 font-bold text-white hover:bg-indigo-500"
                                    @click="handleWorkQueueGroupClick(group)"
                                >
                                    {{
                                        requestOnly
                                            ? 'Xem tiến độ'
                                            : group.can_batch_route
                                              ? 'Định tuyến'
                                              : 'Xử lý'
                                    }}
                                    <Activity class="size-3.5" />
                                </Button>
                            </div>
                        </div>

                        <!-- Single Transfer in Work Queue -->
                        <div
                            v-else
                            v-for="transfer in group.items"
                            :key="`queue-${transfer.id}`"
                            class="flex flex-col gap-3 rounded-xl border border-border/80 bg-background/60 p-3 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <button
                                type="button"
                                class="min-w-0 flex-1 text-left"
                                @click="openDetails(transfer)"
                            >
                                <div class="flex flex-wrap items-center gap-2">
                                    <span
                                        class="font-mono text-[10px] font-bold text-muted-foreground"
                                        >TR-{{
                                            String(transfer.id).padStart(5, '0')
                                        }}</span
                                    >
                                    <span
                                        class="truncate text-sm font-bold text-foreground"
                                        >{{ transfer.ingredient }}</span
                                    >
                                    <span
                                        v-if="transfer.priority === 'urgent'"
                                        class="inline-flex items-center gap-1 rounded-full border border-orange-400/30 bg-orange-500/10 px-2 py-0.5 text-[10px] font-bold text-orange-300"
                                    >
                                        <AlertTriangle class="size-3" />
                                        Khẩn cấp
                                    </span>
                                    <span
                                        v-if="isOverdue(transfer)"
                                        class="inline-flex items-center gap-1 rounded-full border border-rose-400/30 bg-rose-500/10 px-2 py-0.5 text-[10px] font-bold text-rose-300"
                                    >
                                        <Timer class="size-3" /> Quá SLA
                                        {{ overdueHours(transfer) }}h
                                    </span>
                                </div>
                                <p
                                    class="mt-1 truncate text-xs text-muted-foreground"
                                >
                                    {{
                                        transfer.from_branch ||
                                        'Chưa chọn nguồn'
                                    }}
                                    → {{ transfer.to_branch }} ·
                                    {{ nextAction(transfer) }}
                                </p>
                            </button>
                            <div class="flex shrink-0 items-center gap-2">
                                <span
                                    class="text-xs font-semibold text-muted-foreground"
                                    >{{
                                        formatNumber(
                                            transfer.quantity_requested,
                                        )
                                    }}
                                    {{ transfer.unit }}</span
                                >
                                <Button
                                    size="sm"
                                    class="gap-1.5 bg-teal-600 font-bold text-white hover:bg-teal-500"
                                    @click="openDetails(transfer)"
                                >
                                    {{ requestOnly ? 'Xem tiến độ' : 'Xử lý' }}
                                    <Activity class="size-3.5" />
                                </Button>
                            </div>
                        </div>
                    </template>
                </div>
                <div
                    v-else
                    class="mt-4 rounded-xl border border-dashed border-emerald-400/20 bg-emerald-950/10 p-5 text-center"
                >
                    <CheckCircle2 class="mx-auto size-6 text-emerald-400" />
                    <p class="mt-2 text-sm font-bold text-foreground">
                        Không còn việc tồn
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Các phiếu thuộc quyền của bạn đã được xử lý hoặc đang
                        chờ bước tiếp theo.
                    </p>
                </div>
            </div>

            <div
                class="rounded-2xl border border-border bg-card/70 p-4 shadow-sm sm:p-5"
            >
                <div class="flex items-center gap-2">
                    <Activity class="size-4 text-indigo-400" />
                    <h2 class="font-black text-foreground">
                        Sức khỏe luồng điều chuyển
                    </h2>
                </div>
                <p class="mt-1 text-xs text-muted-foreground">
                    Tổng hợp theo các phiếu đang hiển thị trong phạm vi dữ liệu
                    của bạn.
                </p>
                <div class="mt-4 grid grid-cols-2 gap-2">
                    <div class="rounded-xl bg-muted/40 p-3">
                        <p
                            class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                        >
                            Đang mở
                        </p>
                        <p class="mt-1 text-lg font-black text-foreground">
                            {{ operationalStats.activeCount }}
                        </p>
                        <p class="text-[11px] text-muted-foreground">
                            phiếu chưa đối soát
                        </p>
                    </div>
                    <div class="rounded-xl bg-rose-500/5 p-3">
                        <p
                            class="text-[10px] font-bold tracking-wider text-rose-300 uppercase"
                        >
                            Quá SLA
                        </p>
                        <p class="mt-1 text-lg font-black text-rose-300">
                            {{ operationalStats.overdueCount }}
                        </p>
                        <p class="text-[11px] text-muted-foreground">
                            cần đôn đốc
                        </p>
                    </div>
                    <div class="rounded-xl bg-violet-500/5 p-3">
                        <p
                            class="text-[10px] font-bold tracking-wider text-violet-300 uppercase"
                        >
                            Giá trị đang đi
                        </p>
                        <p class="mt-1 text-lg font-black text-violet-200">
                            {{
                                formatCurrency(operationalStats.inTransitValue)
                            }}
                        </p>
                        <p class="text-[11px] text-muted-foreground">
                            tạm tính theo giá xuất
                        </p>
                    </div>
                    <div class="rounded-xl bg-amber-500/5 p-3">
                        <p
                            class="text-[10px] font-bold tracking-wider text-amber-300 uppercase"
                        >
                            Chênh lệch
                        </p>
                        <p class="mt-1 text-lg font-black text-amber-200">
                            {{
                                formatCurrency(
                                    operationalStats.discrepancyValue,
                                )
                            }}
                        </p>
                        <p class="text-[11px] text-muted-foreground">
                            giá trị cần xác minh
                        </p>
                    </div>
                </div>
                <div
                    class="mt-3 flex items-center gap-2 rounded-xl border border-border/70 px-3 py-2.5 text-xs text-muted-foreground"
                >
                    <CalendarClock class="size-4 text-teal-400" />
                    <span>Thời gian hoàn tất trung bình:</span>
                    <strong class="text-foreground">{{
                        formatDuration(operationalStats.averageCycleHours)
                    }}</strong>
                </div>
            </div>
        </section>



        <!-- Active Action Filter Banner (Xuất / Nhận nguyên liệu) -->
        <div
            v-if="actionFilter !== 'all'"
            id="transfers-filter-banner"
            class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border p-4 shadow-sm transition-all scroll-mt-24"
            :class="
                actionFilter === 'dispatch'
                    ? 'border-amber-500/40 bg-amber-950/20 text-amber-200'
                    : 'border-emerald-500/40 bg-emerald-950/20 text-emerald-200'
            "
        >
            <div class="flex items-center gap-3">
                <div
                    class="flex size-10 items-center justify-center rounded-xl shadow-sm"
                    :class="
                        actionFilter === 'dispatch'
                            ? 'border border-amber-500/30 bg-amber-500/20 text-amber-400'
                            : 'border border-emerald-500/30 bg-emerald-500/20 text-emerald-400'
                    "
                >
                    <PackageOpen v-if="actionFilter === 'dispatch'" class="size-5" />
                    <PackageCheck v-else class="size-5" />
                </div>
                <div>
                    <h3 class="text-sm font-bold text-foreground">
                        {{
                            actionFilter === 'dispatch'
                                ? `Đang lọc: ${filteredTransfers.length} nguyên liệu chờ xuất kho`
                                : `Đang lọc: ${filteredTransfers.length} nguyên liệu chờ nhận kho`
                        }}
                    </h3>
                    <p class="text-xs text-muted-foreground">
                        {{
                            actionFilter === 'dispatch'
                                ? 'Danh sách các phiếu cần xác nhận xuất kho và chuyển giao vận chuyển.'
                                : 'Danh sách các phiếu đang chuyển giao đến, chờ kiểm đếm và nhận hàng.'
                        }}
                    </p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <Button
                    v-if="actionFilter === 'dispatch' && dispatchableTransfers.length > 0"
                    size="sm"
                    class="gap-1.5 rounded-xl bg-amber-500 font-bold text-white shadow-sm hover:bg-amber-600"
                    @click="openBulkDispatch()"
                >
                    <PackageOpen class="size-4" /> Xuất hàng loạt ({{ dispatchableTransfers.length }})
                </Button>
                <Button
                    v-if="actionFilter === 'receive' && receivableTransfers.length > 0"
                    size="sm"
                    class="gap-1.5 rounded-xl bg-emerald-600 font-bold text-white shadow-sm hover:bg-emerald-700"
                    @click="openBulkReceive()"
                >
                    <PackageCheck class="size-4" /> Nhận hàng loạt ({{ receivableTransfers.length }})
                </Button>
                <Button
                    size="sm"
                    variant="outline"
                    class="gap-1.5 rounded-xl border-border bg-background/80 text-muted-foreground hover:bg-muted hover:text-foreground"
                    @click="clearActionFilter"
                >
                    <X class="size-3.5" /> Bỏ lọc
                </Button>
            </div>
        </div>

        <section
            id="transfers-list-section"
            class="flex flex-col gap-3 rounded-2xl border border-border bg-card/50 p-3 sm:flex-row sm:items-center scroll-mt-24"
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
                @change="actionFilter = 'all'"
                class="h-10 rounded-md border border-input bg-background px-3 text-sm text-foreground sm:w-56"
            >
                <option value="all">Tất cả trạng thái</option>
                <option value="requested">
                    {{
                        requestOnly
                            ? 'Chờ Chủ doanh nghiệp xem xét'
                            : 'Chờ định tuyến'
                    }}
                </option>
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

        <section v-if="filteredTransfers.length" class="flex flex-col gap-4">
            <template v-for="group in groupedTransfers" :key="group.key">
                <!-- Grouped Request Card (When multiple items in the same request group) -->
                <article
                    v-if="group.is_group"
                    class="rounded-2xl border border-indigo-500/30 bg-gradient-to-b from-indigo-950/20 via-card/80 to-card/90 p-5 shadow-md ring-1 ring-indigo-500/10"
                >
                    <div
                        class="flex flex-col gap-4 border-b border-border/70 pb-4 xl:flex-row xl:items-start xl:justify-between"
                    >
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span
                                    class="inline-flex items-center gap-1 rounded-md border border-indigo-500/30 bg-indigo-500/15 px-2 py-0.5 text-[11px] font-bold text-indigo-300"
                                >
                                    <ArrowLeftRight class="size-3.5" /> PHIẾU YÊU CẦU ĐA NGUYÊN LIỆU ({{ group.items.length }} món)
                                </span>
                                <span
                                    class="font-mono text-[11px] font-bold text-muted-foreground"
                                >
                                    #{{ group.request_group_id ? group.request_group_id.slice(0, 8).toUpperCase() : group.key }}
                                </span>
                                <span
                                    v-if="group.priority === 'urgent'"
                                    class="inline-flex items-center gap-1 rounded-full border border-orange-400/30 bg-orange-500/10 px-2 py-0.5 text-[10px] font-bold text-orange-300"
                                >
                                    <AlertTriangle class="size-3" /> Khẩn cấp
                                </span>
                                <span
                                    v-if="group.has_overdue"
                                    class="inline-flex items-center gap-1 rounded-full border border-rose-400/30 bg-rose-500/10 px-2 py-0.5 text-[10px] font-bold text-rose-300"
                                >
                                    <Timer class="size-3" /> Quá SLA
                                </span>
                            </div>
                            <div
                                class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground"
                            >
                                <span class="inline-flex items-center gap-1">
                                    <MapPin class="size-3 text-indigo-400" /> Cần cấp về:
                                    <strong class="text-foreground">{{ group.to_branch }}</strong>
                                </span>
                                <span class="inline-flex items-center gap-1">
                                    <Clock3 class="size-3" /> {{ group.created_at }}
                                </span>
                                <span>
                                    Người yêu cầu:
                                    <strong class="text-foreground">{{ group.requested_by || '—' }}</strong>
                                </span>
                            </div>
                            <p class="mt-2 rounded-lg border border-border/50 bg-muted/30 p-2.5 text-xs text-muted-foreground">
                                <b class="text-foreground">Lý do chung:</b> {{ group.reason }}
                            </p>
                        </div>

                        <!-- Batch Action Buttons for the Whole Group -->
                        <div class="flex shrink-0 flex-wrap items-center gap-2">
                            <!-- Group-level Batch Receive Button (Green) -->
                            <Button
                                v-if="group.can_batch_receive"
                                size="sm"
                                class="gap-1.5 bg-emerald-600 font-bold text-white shadow-sm hover:bg-emerald-700"
                                @click="openBulkReceive(group)"
                            >
                                <PackageCheck class="size-3.5" /> Nhận nguyên liệu
                            </Button>
                            <!-- Group-level Batch Dispatch Button (Amber) -->
                            <Button
                                v-if="group.can_batch_dispatch"
                                size="sm"
                                class="gap-1.5 bg-amber-500 font-bold text-white shadow-sm hover:bg-amber-600"
                                @click="openBulkDispatch(group)"
                            >
                                <PackageOpen class="size-3.5" /> Xuất nguyên liệu
                            </Button>
                            <Button
                                v-if="group.can_batch_route"
                                size="sm"
                                class="gap-1.5 bg-indigo-600 font-bold text-white shadow-sm hover:bg-indigo-700"
                                @click="openBatchRoute(group)"
                            >
                                <RouteIcon class="size-3.5" /> Định tuyến
                            </Button>
                            <Button
                                v-if="group.can_batch_reject"
                                size="sm"
                                variant="outline"
                                class="gap-1.5 border-rose-500/30 text-rose-500 hover:bg-rose-500/10"
                                @click="openBatchReject(group)"
                            >
                                <XCircle class="size-3.5" /> Từ chối cả phiếu
                            </Button>
                            <Button
                                v-if="group.can_batch_cancel"
                                size="sm"
                                variant="outline"
                                class="gap-1.5 border-rose-500/30 text-rose-500 hover:bg-rose-500/10"
                                @click="openBatchCancel(group)"
                            >
                                <Ban class="size-3.5" /> Hủy cả phiếu
                            </Button>
                        </div>
                    </div>

                    <!-- Items Table inside the group -->
                    <div class="mt-4 overflow-x-auto">
                        <table class="w-full border-collapse text-left text-xs">
                            <thead>
                                <tr class="border-b border-border/80 text-[11px] font-bold tracking-wider text-muted-foreground uppercase">
                                    <th class="px-3 py-2.5">Mã & Tên nguyên liệu</th>
                                    <th class="px-3 py-2.5">SL Yêu cầu</th>
                                    <th class="px-3 py-2.5">Kho nguồn & Mã GN</th>
                                    <th class="px-3 py-2.5">Trạng thái</th>
                                    <th class="px-3 py-2.5 text-right">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border/50">
                                <tr
                                    v-for="transfer in group.items"
                                    :key="transfer.id"
                                    class="transition hover:bg-muted/30"
                                >
                                    <td class="px-3 py-3">
                                        <div class="flex items-center gap-2">
                                            <span class="font-mono text-[10px] font-semibold text-muted-foreground">
                                                TR-{{ String(transfer.id).padStart(5, '0') }}
                                            </span>
                                            <span class="font-bold text-foreground">{{ transfer.ingredient }}</span>
                                        </div>
                                    </td>
                                    <td class="px-3 py-3 font-semibold text-foreground">
                                        {{ formatNumber(transfer.quantity_requested) }} {{ transfer.unit }}
                                    </td>
                                    <td class="px-3 py-3 text-muted-foreground">
                                        <div v-if="transfer.from_branch" class="flex flex-col">
                                            <span class="font-medium text-foreground">{{ transfer.from_branch }}</span>
                                            <span v-if="transfer.handover_code" class="font-mono text-[10px] text-violet-300">
                                                GN: {{ transfer.handover_code }}
                                            </span>
                                        </div>
                                        <span v-else class="italic text-[11px]">Chưa chọn kho cấp</span>
                                    </td>
                                    <td class="px-3 py-3">
                                        <span
                                            class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[10px] font-bold"
                                            :class="statusConfig[transfer.status].className"
                                        >
                                            <component :is="statusConfig[transfer.status].icon" class="size-3" />
                                            {{ statusLabel(transfer.status) }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-3 text-right">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                class="h-7 px-2 text-xs"
                                                @click="openDetails(transfer)"
                                            >
                                                <Eye class="mr-1 size-3" /> Chi tiết
                                            </Button>
                                            <Button
                                                v-if="transfer.can_resolve"
                                                size="sm"
                                                class="h-7 bg-rose-600 px-2 text-xs font-bold text-white hover:bg-rose-700"
                                                @click="openResolve(transfer)"
                                            >
                                                <ClipboardCheck class="mr-1 size-3" /> Chốt lệch
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </article>

                <!-- Single Item Card (When transfer is standalone or 1 item) -->
                <article
                    v-else
                    v-for="transfer in group.items"
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
                                    v-if="transfer.priority === 'urgent'"
                                    class="inline-flex items-center gap-1 rounded-full border border-orange-400/30 bg-orange-500/10 px-2 py-1 text-[10px] font-bold text-orange-300"
                                >
                                    <AlertTriangle class="size-3" /> Khẩn cấp
                                </span>
                                <span
                                    class="rounded-full border px-2 py-1 text-[10px] font-bold"
                                    :class="statusConfig[transfer.status].className"
                                >
                                    <component
                                        :is="statusConfig[transfer.status].icon"
                                        class="mr-1 inline size-3"
                                    />{{ statusLabel(transfer.status) }}
                                </span>
                                <span
                                    v-if="isOverdue(transfer)"
                                    class="inline-flex items-center gap-1 rounded-full border border-rose-400/30 bg-rose-500/10 px-2 py-1 text-[10px] font-bold text-rose-300"
                                >
                                    <Timer class="size-3" /> Quá SLA
                                    {{ overdueHours(transfer) }}h
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
                            :href="`/secure-files/download?path=${encodeURIComponent(transfer.receiving_evidence_path)}`"
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
                        <p
                            v-if="transfer.reject_reason"
                            class="font-semibold text-rose-400"
                        >
                            <b>Từ chối:</b> {{ transfer.reject_reason }}
                        </p>
                        <p
                            v-if="transfer.cancel_reason"
                            class="font-semibold text-rose-400"
                        >
                            <b>Đã hủy:</b> {{ transfer.cancel_reason }}
                        </p>
                    </div>
                </article>
            </template>
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
        <!-- ════════ Bulk Dispatch Modal (Xuất hàng loạt) ════════ -->
        <div
            v-if="showBulkDispatch"
            class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-950/80 p-4 py-8 backdrop-blur-sm"
            @click.self="closeBulkDispatch"
        >
            <div
                class="w-full rounded-3xl border border-border bg-card p-6 shadow-2xl transition-all"
                :class="bulkDispatchStep === 2 ? 'max-w-5xl' : 'max-w-4xl'"
            >
                <!-- ─ Step 1: Review & Evidence ─ -->
                <template v-if="bulkDispatchStep === 1">
                    <div class="mb-6 flex items-start justify-between gap-3">
                        <div>
                            <p class="text-[10px] font-bold tracking-wider text-amber-400 uppercase">
                                {{
                                    bulkTargetGroup
                                        ? `Phiếu yêu cầu #${bulkTargetGroup.request_group_id ? bulkTargetGroup.request_group_id.slice(0, 8).toUpperCase() : bulkTargetGroup.key}`
                                        : 'Xuất nguyên liệu hàng loạt'
                                }}
                            </p>
                            <h2 class="mt-1 text-2xl font-black">
                                Xác nhận xuất kho ({{ bulkLines.length }} nguyên liệu)
                            </h2>
                            <p class="mt-1 text-xs text-muted-foreground">
                                <template v-if="bulkTargetGroup">
                                    Xuất từ <b>{{ bulkLines[0]?.transfer.from_branch || '—' }}</b> sang <b>{{ bulkTargetGroup.to_branch }}</b> · Người yêu cầu: <b>{{ bulkTargetGroup.requested_by || '—' }}</b>
                                </template>
                                <template v-else>
                                    Xem lại số lượng từng nguyên liệu, nộp ảnh minh chứng rồi ấn tiếp để xem phiếu trước khi xác nhận.
                                </template>
                            </p>
                        </div>
                        <Button variant="ghost" size="icon" @click="closeBulkDispatch"><X class="size-4" /></Button>
                    </div>

                    <!-- items table -->
                    <div class="overflow-x-auto rounded-2xl border border-border">
                        <table class="w-full border-collapse text-left text-sm">
                            <thead>
                                <tr class="border-b border-border bg-muted/40 text-[11px] font-bold tracking-wider text-muted-foreground uppercase">
                                    <th class="px-4 py-3">Mã · Nguyên liệu</th>
                                    <th class="px-4 py-3">Kho xuất</th>
                                    <th class="px-4 py-3">Nhận về</th>
                                    <th class="px-4 py-3 text-center">Tồn kho lúc xuất</th>
                                    <th class="px-4 py-3 text-center">SL yêu cầu</th>
                                    <th class="px-4 py-3 text-center">SL thực xuất</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border/60">
                                <tr
                                    v-for="(line, idx) in bulkLines"
                                    :key="line.transfer.id"
                                    class="transition hover:bg-muted/20"
                                    :id="`bulk-line-${idx}`"
                                >
                                    <td class="px-4 py-3">
                                        <p class="font-mono text-[10px] text-muted-foreground">TR-{{ String(line.transfer.id).padStart(5, '0') }}</p>
                                        <p class="font-bold text-foreground">{{ line.transfer.ingredient }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-muted-foreground">{{ line.transfer.from_branch }}</td>
                                    <td class="px-4 py-3 text-sm text-muted-foreground">{{ line.transfer.to_branch }}</td>
                                    <td class="px-4 py-3 text-center">
                                        <span
                                            class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-bold"
                                            :class="
                                                line.availableQty >= line.transfer.quantity_requested
                                                    ? 'border border-emerald-500/30 bg-emerald-500/10 text-emerald-400'
                                                    : 'border border-amber-500/30 bg-amber-500/10 text-amber-300'
                                            "
                                        >
                                            {{ formatNumber(line.availableQty) }} {{ line.transfer.unit }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-center font-semibold">
                                        {{ formatNumber(line.transfer.quantity_requested) }} {{ line.transfer.unit }}
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <div class="flex items-center justify-center gap-1">
                                            <input
                                                v-model.number="line.qty"
                                                type="number"
                                                step="0.001"
                                                min="0.001"
                                                :max="line.maxQty"
                                                class="w-24 rounded-lg border border-amber-400/40 bg-amber-950/20 px-2 py-1 text-center text-sm font-bold text-amber-200 focus:outline-none focus:ring-1 focus:ring-amber-400"
                                            />
                                            <span class="text-xs text-muted-foreground">{{ line.transfer.unit }}</span>
                                        </div>
                                        <p v-if="line.maxQty < line.transfer.quantity_requested" class="mt-0.5 text-[10px] text-amber-400">
                                            Tối đa: {{ formatNumber(line.maxQty) }} (2/3 tồn)
                                        </p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- note + evidence -->
                    <div class="mt-5 grid gap-4 sm:grid-cols-2">
                        <div class="flex flex-col gap-1.5">
                            <Label class="text-xs font-bold">Ghi chú xuất kho chung</Label>
                            <textarea
                                v-model="bulkDispatchNote"
                                rows="3"
                                class="rounded-xl border border-input bg-background px-3 py-2 text-sm"
                                placeholder="Tình trạng đóng gói, phương tiện vận chuyển, người bàn giao..."
                                id="bulk-dispatch-note"
                            />
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <Label class="text-xs font-bold">
                                📸 Ảnh / PDF minh chứng xuất kho
                                <span class="ml-1 text-rose-400">*</span>
                            </Label>
                            <div
                                class="flex flex-1 flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-amber-400/30 bg-amber-950/10 p-4 transition hover:bg-amber-950/20"
                                @dragover.prevent
                                @drop.prevent="(e) => { if (e.dataTransfer?.files?.[0]) { bulkDispatchEvidenceFile = e.dataTransfer.files[0]; } }"
                            >
                                <input
                                    id="bulk-evidence-input"
                                    type="file"
                                    accept="image/*,.pdf"
                                    class="hidden"
                                    @change="onBulkEvidenceChange"
                                />
                                <label for="bulk-evidence-input" class="cursor-pointer text-center">
                                    <div v-if="bulkDispatchEvidenceFile" class="flex items-center gap-2 text-sm font-semibold text-emerald-400">
                                        <Check class="size-4" /> {{ bulkDispatchEvidenceFile.name }}
                                    </div>
                                    <div v-else class="text-xs text-muted-foreground">
                                        <p class="font-bold text-amber-300">Nhấn để chọn file</p>
                                        <p class="mt-1">hoặc kéo thả vào đây</p>
                                        <p class="mt-1 text-[10px]">JPG / PNG / PDF · tối đa 5 MB</p>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- actions -->
                    <div class="mt-6 flex items-center justify-between gap-3">
                        <div class="text-xs text-muted-foreground">
                            <span class="font-bold text-amber-300">{{ bulkLines.length }}</span> nguyên liệu sẽ được xuất kho
                        </div>
                        <div class="flex gap-2">
                            <Button variant="outline" @click="closeBulkDispatch">Hủy</Button>
                            <Button
                                :disabled="!bulkDispatchEvidenceFile"
                                class="gap-2 bg-amber-500 font-bold text-white hover:bg-amber-600"
                                @click="goToBulkPreview"
                                id="bulk-preview-btn"
                            >
                                Xem phiếu xác nhận →
                            </Button>
                        </div>
                    </div>
                </template>

                <!-- ─ Step 2: Phiếu Điều Chuyển (A4 Paper Document) ─ -->
                <template v-else>
                    <div class="mb-4 flex items-start justify-between gap-3">
                        <div>
                            <p class="text-[10px] font-bold tracking-wider text-emerald-400 uppercase">Xác nhận cuối</p>
                            <h2 class="mt-1 text-xl font-black">Phiếu điều chuyển nguyên liệu (Khổ A4)</h2>
                            <p class="mt-1 text-xs text-muted-foreground">Văn bản trắng chuẩn mẫu in A4. Kiểm tra lại thông tin rồi ấn xác nhận để xuất kho.</p>
                        </div>
                        <Button variant="ghost" size="icon" @click="closeBulkDispatch"><X class="size-4" /></Button>
                    </div>

                    <!-- A4-style Pure White Paper Slip -->
                    <div
                        id="bulk-dispatch-slip"
                        class="mx-auto rounded-lg border border-neutral-300 bg-white p-6 sm:p-8 text-black shadow-2xl font-sans text-[11px] leading-normal print:m-0 print:border-none print:p-0 print:shadow-none"
                        style="background-color: #ffffff !important; color: #000000 !important;"
                    >
                        <!-- Header -->
                        <div class="grid grid-cols-2 items-start gap-4 border-b-2 border-black pb-3">
                            <div>
                                <div class="flex items-center gap-2">
                                    <div class="flex size-7 items-center justify-center rounded border border-black font-black text-black">
                                        ⚡
                                    </div>
                                    <h4 class="text-xs font-black uppercase tracking-wide">CÔNG TY TNHH AVENTURA</h4>
                                </div>
                                <p class="mt-1 text-[10px] text-neutral-700">Chuỗi cung cấp thực phẩm &amp; dịch vụ nhà hàng</p>
                                <p class="text-[10px] text-neutral-700">📍 Số 123 Nguyễn Văn Cừ, P. Bồ Đề, Q. Long Biên, Hà Nội</p>
                                <p class="text-[10px] text-neutral-700">☎ Hotline: 024 1234 5678</p>
                            </div>
                            <div class="text-center">
                                <p class="text-[11px] font-bold uppercase tracking-wide">CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM</p>
                                <p class="text-[10px] font-semibold">Độc lập – Tự do – Hạnh phúc</p>
                                <p class="text-[9px] tracking-widest">★ ★ ★</p>
                                <p class="mt-1 text-right text-[10px] italic text-neutral-700">
                                    Hà Nội, ngày {{ bulkToday.day }} tháng {{ bulkToday.month }} năm {{ bulkToday.year }}
                                </p>
                            </div>
                        </div>

                        <!-- Title & Number -->
                        <div class="mt-3 text-center">
                            <h3 class="text-base font-black uppercase tracking-wide">PHIẾU ĐIỀU CHUYỂN NGUYÊN LIỆU</h3>
                            <div class="mt-1 inline-block border border-black px-3 py-0.5 text-[11px] font-mono font-bold">
                                Số: DC-NL/{{ bulkToday.year }}/{{
                                    bulkTargetGroup?.request_group_id
                                        ? bulkTargetGroup.request_group_id.slice(0, 8).toUpperCase()
                                        : (bulkLines[0]?.transfer.from_branch?.slice(0, 3).toUpperCase() ?? 'KHO')
                                }}-{{ bulkLines.length }}
                            </div>
                        </div>

                        <!-- 1. THÔNG TIN CHUNG -->
                        <div class="mt-3 border border-black p-2">
                            <p class="font-bold uppercase text-[10px] text-neutral-800">1. THÔNG TIN CHUNG</p>
                            <div class="mt-1 grid grid-cols-2 gap-x-4 gap-y-1">
                                <p><b>Ngày lập phiếu:</b> {{ bulkToday.day }}/{{ bulkToday.month }}/{{ bulkToday.year }}</p>
                                <p><b>Giờ:</b> {{ bulkToday.time }}</p>
                                <div class="col-span-2 flex flex-wrap items-center gap-x-3 gap-y-0.5">
                                    <b>Lý do điều chuyển:</b>
                                    <span>[ ] Cân đối tồn kho</span>
                                    <span>[ ] Phục vụ sản xuất/kinh doanh</span>
                                    <span class="font-bold">[x] Hỗ trợ chi nhánh</span>
                                    <span v-if="bulkDispatchNote || bulkTargetGroup?.reason">[ ] Khác: {{ bulkDispatchNote || bulkTargetGroup?.reason }}</span>
                                </div>
                                <p><b>Người lập phiếu:</b> {{ bulkTargetGroup?.requested_by || 'Nguyễn Văn Quản Lý' }}</p>
                                <p><b>Chức vụ:</b> Quản lý chi nhánh / Điều phối viên</p>
                            </div>
                        </div>

                        <!-- 2. & 3. THÔNG TIN BÊN XUẤT / BÊN NHẬN -->
                        <div class="mt-2 grid grid-cols-2 gap-2">
                            <div class="border border-black p-2">
                                <p class="font-bold uppercase text-[10px] text-neutral-800">2. THÔNG TIN BÊN ĐIỀU CHUYỂN (NƠI XUẤT)</p>
                                <div class="mt-1 space-y-0.5">
                                    <p><b>Kho xuất:</b> {{ bulkLines[0]?.transfer.from_branch ?? 'Kho Tổng' }} (Kho chi nhánh)</p>
                                    <p><b>Địa chỉ kho:</b> Khu vực kho chi nhánh xuất hàng</p>
                                    <p><b>Người phụ trách kho:</b> Nguyễn Văn Quản Lý</p>
                                    <p><b>SĐT:</b> 024 1234 5678</p>
                                </div>
                            </div>
                            <div class="border border-black p-2">
                                <p class="font-bold uppercase text-[10px] text-neutral-800">3. THÔNG TIN BÊN NHẬN ĐIỀU CHUYỂN (NƠI NHẬN)</p>
                                <div class="mt-1 space-y-0.5">
                                    <p><b>Kho nhận:</b> {{ bulkLines[0]?.transfer.to_branch ?? '—' }}</p>
                                    <p><b>Địa chỉ kho:</b> Khu vực nhận hàng chi nhánh</p>
                                    <p><b>Người phụ trách kho:</b> {{ bulkTargetGroup?.requested_by || 'Thủ kho nhận' }}</p>
                                    <p><b>SĐT:</b> 024 1234 5678</p>
                                </div>
                            </div>
                        </div>

                        <!-- 4. HÌNH THỨC VẬN CHUYỂN -->
                        <div class="mt-2 border border-black p-2">
                            <p class="font-bold uppercase text-[10px] text-neutral-800">4. HÌNH THỨC VẬN CHUYỂN</p>
                            <div class="mt-1 grid grid-cols-3 gap-2">
                                <div class="col-span-3 flex items-center gap-4">
                                    <span class="font-bold">[x] Tự vận chuyển</span>
                                    <span>[ ] Đơn vị vận chuyển</span>
                                    <span>[ ] Khác: .......................................</span>
                                </div>
                                <p><b>Phương tiện vận chuyển:</b> Xe máy / Xe tải nội bộ</p>
                                <p><b>Biển số xe:</b> —</p>
                                <p><b>Dự kiến giao đến:</b> {{ bulkToday.day }}/{{ bulkToday.month }}/{{ bulkToday.year }} (trong ngày)</p>
                            </div>
                        </div>

                        <!-- 5. DANH SÁCH NGUYÊN LIỆU ĐIỀU CHUYỂN -->
                        <div class="mt-2">
                            <p class="mb-1 font-bold uppercase text-[10px] text-neutral-800">5. DANH SÁCH NGUYÊN LIỆU ĐIỀU CHUYỂN</p>
                            <table class="w-full border-collapse border border-black text-[10px]">
                                <thead>
                                    <tr class="bg-neutral-100 font-bold text-black">
                                        <th rowspan="2" class="border border-black px-1.5 py-1 text-center">STT</th>
                                        <th rowspan="2" class="border border-black px-1.5 py-1 text-left">Mã nguyên liệu</th>
                                        <th rowspan="2" class="border border-black px-1.5 py-1 text-left">Tên nguyên liệu</th>
                                        <th rowspan="2" class="border border-black px-1.5 py-1 text-center">Đơn vị tính</th>
                                        <th colspan="2" class="border border-black px-1.5 py-1 text-center">Số lượng điều chuyển</th>
                                        <th rowspan="2" class="border border-black px-1.5 py-1 text-center">Tồn kho lúc xuất</th>
                                        <th rowspan="2" class="border border-black px-1.5 py-1 text-right">Đơn giá (VND)</th>
                                        <th rowspan="2" class="border border-black px-1.5 py-1 text-right">Thành tiền (VND)</th>
                                        <th rowspan="2" class="border border-black px-1.5 py-1 text-left">Ghi chú</th>
                                    </tr>
                                    <tr class="bg-neutral-100 font-bold text-black">
                                        <th class="border border-black px-1.5 py-0.5 text-center">Thực xuất</th>
                                        <th class="border border-black px-1.5 py-0.5 text-center">Thực nhận</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="(line, i) in bulkLines"
                                        :key="line.transfer.id"
                                    >
                                        <td class="border border-black px-1.5 py-1 text-center">{{ i + 1 }}</td>
                                        <td class="border border-black px-1.5 py-1 font-mono">TR-{{ String(line.transfer.id).padStart(5, '0') }}</td>
                                        <td class="border border-black px-1.5 py-1 font-bold">{{ line.transfer.ingredient }}</td>
                                        <td class="border border-black px-1.5 py-1 text-center">{{ line.transfer.unit }}</td>
                                        <td class="border border-black px-1.5 py-1 text-center font-black">{{ formatNumber(line.qty) }}</td>
                                        <td class="border border-black px-1.5 py-1 text-center text-neutral-400">..........</td>
                                        <td class="border border-black px-1.5 py-1 text-center font-semibold">{{ formatNumber(line.availableQty) }}</td>
                                        <td class="border border-black px-1.5 py-1 text-right">{{ formatCurrency(line.transfer.source_unit_cost || 0) }}</td>
                                        <td class="border border-black px-1.5 py-1 text-right font-semibold">{{ formatCurrency(line.qty * (line.transfer.source_unit_cost || 0)) }}</td>
                                        <td class="border border-black px-1.5 py-1 text-neutral-600">
                                            {{ line.qty < line.transfer.quantity_requested ? `Xuất 2/3 tồn (${formatNumber(line.maxQty)})` : '' }}
                                        </td>
                                    </tr>
                                    <tr class="bg-neutral-100 font-bold">
                                        <td colspan="4" class="border border-black px-1.5 py-1 text-right uppercase">TỔNG CỘNG</td>
                                        <td class="border border-black px-1.5 py-1 text-center font-black">{{ bulkLines.reduce((s, l) => s + l.qty, 0).toFixed(3) }}</td>
                                        <td class="border border-black px-1.5 py-1 text-center">—</td>
                                        <td class="border border-black px-1.5 py-1 text-center">—</td>
                                        <td class="border border-black px-1.5 py-1 text-right">—</td>
                                        <td class="border border-black px-1.5 py-1 text-right font-black">{{ formatCurrency(bulkTotalCost) }}</td>
                                        <td class="border border-black"></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- 6. GHI CHÚ / YÊU CẦU -->
                        <div class="mt-2 border border-black p-2">
                            <p class="font-bold uppercase text-[10px] text-neutral-800">6. GHI CHÚ / YÊU CẦU</p>
                            <p class="mt-0.5"><b>Ghi chú:</b> {{ bulkDispatchNote || bulkTargetGroup?.reason || 'Nguyên liệu bảo quản và đóng gói đúng tiêu chuẩn vệ sinh an toàn thực phẩm.' }}</p>
                            <p class="mt-0.5"><b>Minh chứng đính kèm:</b> {{ bulkDispatchEvidenceFile?.name ?? 'Đã nộp ảnh / tài liệu đính kèm hệ thống.' }}</p>
                        </div>

                        <!-- 7. XÁC NHẬN -->
                        <div class="mt-2">
                            <p class="mb-1 font-bold uppercase text-[10px] text-neutral-800">7. XÁC NHẬN</p>

                            <!-- a) Bên điều chuyển -->
                            <div class="border border-black p-1.5">
                                <p class="text-[10px] font-bold uppercase text-neutral-800">a) Bên điều chuyển (Nơi xuất)</p>
                                <div class="mt-1 grid grid-cols-3 gap-2 text-center text-[10px]">
                                    <div>
                                        <p class="font-bold uppercase">NGƯỜI LẬP PHIẾU</p>
                                        <p class="text-[9px] text-neutral-500">(Ký, ghi rõ họ tên)</p>
                                        <div class="mt-8 border-t border-dotted border-neutral-400 pt-0.5 text-neutral-400">................................</div>
                                    </div>
                                    <div>
                                        <p class="font-bold uppercase">THỦ KHO XUẤT</p>
                                        <p class="text-[9px] text-neutral-500">(Ký, ghi rõ họ tên)</p>
                                        <div class="mt-8 border-t border-dotted border-neutral-400 pt-0.5 text-neutral-400">................................</div>
                                    </div>
                                    <div>
                                        <p class="font-bold uppercase">QUẢN LÝ KHO / GIÁM SÁT</p>
                                        <p class="text-[9px] text-neutral-500">(Ký, ghi rõ họ tên)</p>
                                        <div class="mt-8 border-t border-dotted border-neutral-400 pt-0.5 text-neutral-400">................................</div>
                                    </div>
                                </div>
                            </div>

                            <!-- b) Bên nhận điều chuyển -->
                            <div class="mt-1.5 border border-black p-1.5">
                                <p class="text-[10px] font-bold uppercase text-neutral-800">b) Bên nhận điều chuyển (Nơi nhận)</p>
                                <div class="mt-1 grid grid-cols-3 gap-2 text-center text-[10px]">
                                    <div>
                                        <p class="font-bold uppercase">THỦ KHO NHẬN</p>
                                        <p class="text-[9px] text-neutral-500">(Ký, ghi rõ họ tên)</p>
                                        <div class="mt-8 border-t border-dotted border-neutral-400 pt-0.5 text-neutral-400">................................</div>
                                    </div>
                                    <div>
                                        <p class="font-bold uppercase">KIỂM TRA CHẤT LƯỢNG</p>
                                        <p class="text-[9px] text-neutral-500">(Ký, ghi rõ họ tên)</p>
                                        <div class="mt-8 border-t border-dotted border-neutral-400 pt-0.5 text-neutral-400">................................</div>
                                    </div>
                                    <div>
                                        <p class="font-bold uppercase">QUẢN LÝ KHO / GIÁM SÁT</p>
                                        <p class="text-[9px] text-neutral-500">(Ký, ghi rõ họ tên)</p>
                                        <div class="mt-8 border-t border-dotted border-neutral-400 pt-0.5 text-neutral-400">................................</div>
                                    </div>
                                </div>
                            </div>

                            <!-- c) Phận vận chuyển -->
                            <div class="mt-1.5 border border-black p-1.5">
                                <p class="text-[10px] font-bold uppercase text-neutral-800">c) Phận vận chuyển (nếu có)</p>
                                <div class="mt-1 grid grid-cols-3 gap-2 text-center text-[10px]">
                                    <div>
                                        <p class="font-bold uppercase">NGƯỜI GIAO HÀNG</p>
                                        <p class="text-[9px] text-neutral-500">(Ký, ghi rõ họ tên)</p>
                                        <div class="mt-8 border-t border-dotted border-neutral-400 pt-0.5 text-neutral-400">................................</div>
                                    </div>
                                    <div>
                                        <p class="font-bold uppercase">NGƯỜI NHẬN HÀNG</p>
                                        <p class="text-[9px] text-neutral-500">(Ký, ghi rõ họ tên)</p>
                                        <div class="mt-8 border-t border-dotted border-neutral-400 pt-0.5 text-neutral-400">................................</div>
                                    </div>
                                    <div>
                                        <p class="font-bold uppercase">XÁC NHẬN HOÀN THÀNH</p>
                                        <p class="text-[9px] text-neutral-500">(Ký, ghi rõ họ tên)</p>
                                        <div class="mt-8 border-t border-dotted border-neutral-400 pt-0.5 text-neutral-400">................................</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Footer notes -->
                        <div class="mt-3 border-t border-neutral-300 pt-1 text-[9px] text-neutral-600">
                            <p>* Phiếu điều chuyển được lập 02 bản: 01 bản lưu tại kho xuất, 01 bản lưu tại kho nhận.</p>
                            <p>* Liên: 1. Lưu tại Kho xuất; 2. Lưu tại Kho nhận; 3. Lưu tại Bộ phận Kế toán (nếu cần).</p>
                        </div>
                    </div>

                    <!-- actions -->
                    <div class="mt-5 flex items-center justify-between gap-3">
                        <div v-if="bulkDispatchSubmitting" class="text-sm font-semibold text-amber-300">
                            Đang xuất {{ bulkDispatchDone }}/{{ bulkLines.length }} nguyên liệu...
                        </div>
                        <div v-else class="text-xs text-muted-foreground">
                            Sau khi xác nhận, hệ thống sẽ xuất lần lượt <b class="text-amber-300">{{ bulkLines.length }}</b> nguyên liệu.
                        </div>
                        <div class="flex gap-2">
                            <Button variant="outline" @click="goBackToBulkForm" :disabled="bulkDispatchSubmitting">← Quay lại</Button>
                            <Button
                                class="gap-2 bg-emerald-600 font-bold text-white hover:bg-emerald-700"
                                :disabled="bulkDispatchSubmitting"
                                @click="submitBulkDispatch"
                                id="bulk-confirm-btn"
                            >
                                <PackageOpen class="size-4" />
                                {{ bulkDispatchSubmitting ? 'Đang xử lý...' : 'Xác nhận xuất kho tất cả' }}
                            </Button>
                        </div>
                    </div>
                </template>
            </div>
        </div>
        <!-- ════ end bulk dispatch modal ════ -->

        <!-- ══════════════════════════════════════════════════════════════ -->
        <!-- BULK RECEIVE MODAL (2-step: Table check & Pure White A4 Slip) -->
        <!-- ══════════════════════════════════════════════════════════════ -->
        <div
            v-if="showBulkReceive"
            class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-950/80 p-4 py-8 backdrop-blur-sm"
            @click.self="closeBulkReceive"
        >
            <div
                class="w-full rounded-3xl border border-border bg-card p-6 shadow-2xl transition-all"
                :class="bulkReceiveStep === 2 ? 'max-w-5xl' : 'max-w-4xl'"
            >
                <!-- ─ Step 1: Kiểm đếm & Đánh giá chất lượng ─ -->
                <template v-if="bulkReceiveStep === 1">
                    <div class="mb-4 flex items-start justify-between gap-3">
                        <div>
                            <p class="text-[10px] font-bold tracking-wider text-emerald-400 uppercase">Bước 1 / 2</p>
                            <h2 class="mt-1 text-xl font-black">Xác nhận nhận nguyên liệu điều chuyển</h2>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Kiểm tra số lượng thực nhận, tách riêng hàng hỏng để đưa vào khu cách ly và xử lý chênh lệch chống gian lận.
                            </p>
                        </div>
                        <Button variant="ghost" size="icon" @click="closeBulkReceive"><X class="size-4" /></Button>
                    </div>

                    <!-- Handover code (Auto-filled) -->
                    <div class="mb-4 rounded-xl border border-emerald-500/30 bg-emerald-500/5 p-3.5">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div>
                                <Label class="text-xs font-bold text-emerald-300">Mã giao nhận (Handover Code)</Label>
                                <p class="text-[11px] text-muted-foreground">Mã định danh tự động gán đồng bộ theo phiếu điều chuyển.</p>
                            </div>
                            <div class="w-full sm:w-48">
                                <div class="rounded-lg border border-emerald-500/40 bg-background/90 py-2 text-center font-mono font-bold tracking-widest text-emerald-300 uppercase shadow-inner">
                                    {{ bulkReceiveHandoverCode || 'TỰ ĐỘNG' }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Items table -->
                    <div class="overflow-x-auto rounded-xl border border-border">
                        <table class="w-full border-collapse text-left text-xs">
                            <thead class="bg-muted/50 text-[11px] font-bold tracking-wider text-muted-foreground uppercase">
                                <tr>
                                    <th class="px-3 py-2.5 text-center">STT</th>
                                    <th class="px-3 py-2.5">Mã & Tên nguyên liệu</th>
                                    <th class="px-3 py-2.5 text-center">ĐVT</th>
                                    <th class="px-3 py-2.5 text-center">SL đã xuất</th>
                                    <th class="px-3 py-2.5 text-center text-emerald-400">SL đạt chuẩn</th>
                                    <th class="px-3 py-2.5 text-center text-rose-400">SL hỏng / hết hạn (Cách ly)</th>
                                    <th class="px-3 py-2.5 text-center text-violet-400">SL thiếu</th>
                                    <th class="px-3 py-2.5">Tình trạng</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border/60">
                                <tr v-for="(line, i) in bulkReceiveLines" :key="line.transfer.id" class="hover:bg-muted/20">
                                    <td class="px-3 py-2 text-center text-muted-foreground">{{ i + 1 }}</td>
                                    <td class="px-3 py-2">
                                        <div class="font-bold text-foreground">{{ line.transfer.ingredient }}</div>
                                        <div class="font-mono text-[10px] text-muted-foreground">TR-{{ String(line.transfer.id).padStart(5, '0') }}</div>
                                    </td>
                                    <td class="px-3 py-2 text-center text-muted-foreground">{{ line.transfer.unit }}</td>
                                    <td class="px-3 py-2 text-center font-semibold text-foreground">{{ formatNumber(line.dispatchedQty) }}</td>
                                    <td class="px-3 py-2 text-center">
                                        <Input
                                            type="number"
                                            step="0.001"
                                            min="0"
                                            :max="line.dispatchedQty"
                                            v-model.number="line.goodQty"
                                            @input="updateReceiveLineFromGood(line)"
                                            class="h-8 w-24 text-center font-bold text-emerald-400 border-emerald-500/30"
                                        />
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <Input
                                            type="number"
                                            step="0.001"
                                            min="0"
                                            :max="line.dispatchedQty"
                                            v-model.number="line.damagedQty"
                                            @input="updateReceiveLineFromDamaged(line)"
                                            class="h-8 w-24 text-center font-bold text-rose-400 border-rose-500/30"
                                        />
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <Input
                                            type="number"
                                            step="0.001"
                                            min="0"
                                            :max="line.dispatchedQty"
                                            v-model.number="line.shortageQty"
                                            @input="updateReceiveLineFromShortage(line)"
                                            class="h-8 w-24 text-center font-bold text-violet-300 border-violet-500/30"
                                        />
                                        <div v-if="!isLineBalanced(line)" class="mt-1 text-[9px] font-bold text-rose-400">
                                            Tổng {{ getLineSum(line) }} ≠ {{ line.dispatchedQty }}
                                        </div>
                                    </td>
                                    <td class="px-3 py-2">
                                        <span
                                            class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[10px] font-bold"
                                            :class="{
                                                'border-emerald-500/30 bg-emerald-500/10 text-emerald-300': line.condition === 'good',
                                                'border-rose-500/30 bg-rose-500/10 text-rose-300': line.condition === 'damaged',
                                                'border-violet-500/30 bg-violet-500/10 text-violet-300': line.condition === 'shortage',
                                                'border-amber-500/30 bg-amber-500/10 text-amber-300': line.condition === 'mixed',
                                            }"
                                        >
                                            {{
                                                line.condition === 'good'
                                                    ? 'Đạt yêu cầu'
                                                    : line.condition === 'damaged'
                                                      ? 'Hỏng (cách ly)'
                                                      : line.condition === 'shortage'
                                                        ? 'Thiếu hàng'
                                                        : 'Hỗn hợp'
                                            }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Note & Evidence Upload -->
                    <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <Label class="text-xs font-semibold">
                                Ghi chú / Biên bản kiểm nhận <span class="text-rose-400 font-bold">* (Bắt buộc)</span>
                            </Label>
                            <textarea
                                v-model="bulkReceiveNote"
                                rows="3"
                                placeholder="Ghi nhận tình trạng niêm phong, cảm quan nguyên liệu, nhiệt độ vận chuyển..."
                                class="mt-1.5 w-full rounded-xl border border-input bg-background p-2.5 text-xs text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-emerald-500"
                            />
                            <p v-if="!bulkReceiveNote || bulkReceiveNote.trim().length === 0" class="mt-1 text-[10px] text-rose-400">
                                ⚠️ Vui lòng nhập ghi chú kiểm nhận
                            </p>
                        </div>
                        <div>
                            <Label class="text-xs font-semibold">
                                Ảnh / PDF minh chứng kiểm đếm <span class="text-rose-400 font-bold">* (Bắt buộc)</span>
                            </Label>
                            <label
                                class="mt-1.5 flex cursor-pointer flex-col items-center justify-center rounded-xl border border-dashed p-4 text-center transition hover:bg-emerald-500/10"
                                :class="!bulkReceiveEvidenceFile ? 'border-amber-500/40 bg-amber-500/5' : 'border-emerald-500/60 bg-emerald-500/10'"
                            >
                                <input
                                    type="file"
                                    accept="image/*,application/pdf"
                                    class="hidden"
                                    @change="onBulkReceiveEvidenceChange"
                                />
                                <div class="text-xs text-muted-foreground">
                                    <span v-if="bulkReceiveEvidenceFile" class="font-bold text-emerald-400">
                                        ✓ Đã chọn: {{ bulkReceiveEvidenceFile.name }}
                                    </span>
                                    <span v-else class="text-amber-400 font-medium">
                                        Nhấn để tải ảnh kiện hàng / biên bản nhận *
                                    </span>
                                </div>
                                <p class="mt-0.5 text-[10px] text-muted-foreground">Hỗ trợ JPG, PNG, WEBP, PDF tối đa 10MB</p>
                            </label>
                            <p v-if="!bulkReceiveEvidenceFile" class="mt-1 text-[10px] text-rose-400">
                                ⚠️ Vui lòng đính kèm ảnh/PDF minh chứng
                            </p>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="mt-6 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="text-xs">
                            <span v-if="!canGoToBulkReceivePreview" class="font-bold text-amber-400 flex flex-wrap items-center gap-2">
                                <span>⚠️ Cần điền đủ:</span>
                                <span :class="bulkReceiveIsAllBalanced ? 'text-emerald-400' : 'text-rose-400'">Tổng kiểm đếm</span> •
                                <span :class="bulkReceiveNote && bulkReceiveNote.trim().length > 0 ? 'text-emerald-400' : 'text-rose-400'">Ghi chú</span> •
                                <span :class="bulkReceiveEvidenceFile ? 'text-emerald-400' : 'text-rose-400'">Ảnh minh chứng</span>
                            </span>
                            <span v-else class="text-muted-foreground">
                                <span class="font-bold text-emerald-400">{{ bulkReceiveLines.length }}</span> nguyên liệu sẵn sàng nhận (Đã điền đủ thông tin ✓)
                            </span>
                        </div>
                        <div class="flex gap-2">
                            <Button variant="outline" @click="closeBulkReceive">Hủy</Button>
                            <Button
                                :disabled="!canGoToBulkReceivePreview"
                                class="gap-2 bg-emerald-600 font-bold text-white hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed"
                                @click="goToBulkReceivePreview"
                                id="bulk-receive-preview-btn"
                            >
                                Xem phiếu xác nhận →
                            </Button>
                        </div>
                    </div>
                </template>

                <!-- ─ Step 2: Phiếu Nhận Nguyên Liệu (Khổ A4 Nền Trắng) ─ -->
                <template v-else>
                    <div class="mb-4 flex items-start justify-between gap-3">
                        <div>
                            <p class="text-[10px] font-bold tracking-wider text-emerald-400 uppercase">Xác nhận cuối</p>
                            <h2 class="mt-1 text-xl font-black">Phiếu nhận nguyên liệu điều chuyển (Khổ A4)</h2>
                            <p class="mt-1 text-xs text-muted-foreground">Văn bản trắng chuẩn mẫu in A4. Kiểm tra lại thông tin rồi ấn xác nhận để hoàn tất nhập kho.</p>
                        </div>
                        <Button variant="ghost" size="icon" @click="closeBulkReceive"><X class="size-4" /></Button>
                    </div>

                    <!-- A4-style Pure White Paper Slip -->
                    <div
                        id="bulk-receive-slip"
                        class="mx-auto rounded-lg border border-neutral-300 bg-white p-6 sm:p-8 text-black shadow-2xl font-sans text-[11px] leading-normal print:m-0 print:border-none print:p-0 print:shadow-none"
                        style="background-color: #ffffff !important; color: #000000 !important;"
                    >
                        <!-- Header -->
                        <div class="grid grid-cols-2 items-start gap-4 border-b-2 border-black pb-3">
                            <div>
                                <div class="flex items-center gap-2">
                                    <div class="flex size-7 items-center justify-center rounded border border-black font-black text-black">
                                        ⚡
                                    </div>
                                    <h4 class="text-xs font-black uppercase tracking-wide">CÔNG TY TNHH AVENTURA</h4>
                                </div>
                                <p class="mt-1 text-[10px] text-neutral-700">Chuỗi cung cấp thực phẩm &amp; dịch vụ nhà hàng</p>
                                <p class="text-[10px] text-neutral-700">📍 Số 123 Nguyễn Văn Cừ, P. Bồ Đề, Q. Long Biên, Hà Nội</p>
                                <p class="text-[10px] text-neutral-700">☎ Hotline: 024 1234 5678</p>
                            </div>
                            <div class="text-center">
                                <p class="text-[11px] font-bold uppercase tracking-wide">CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM</p>
                                <p class="text-[10px] font-semibold">Độc lập – Tự do – Hạnh phúc</p>
                                <p class="text-[9px] tracking-widest">★ ★ ★</p>
                                <p class="mt-1 text-right text-[10px] italic text-neutral-700">
                                    Hà Nội, ngày {{ bulkToday.day }} tháng {{ bulkToday.month }} năm {{ bulkToday.year }}
                                </p>
                            </div>
                        </div>

                        <!-- Title & Number -->
                        <div class="mt-3 text-center">
                            <h3 class="text-base font-black uppercase tracking-wide">PHIẾU NHẬN NGUYÊN LIỆU ĐIỀU CHUYỂN</h3>
                            <div class="mt-1 inline-block border border-black px-3 py-0.5 text-[11px] font-mono font-bold">
                                Số: PN-DC/{{ bulkToday.year }}/{{
                                    bulkReceiveTargetGroup?.request_group_id
                                        ? bulkReceiveTargetGroup.request_group_id.slice(0, 8).toUpperCase()
                                        : (bulkReceiveLines[0]?.transfer.to_branch?.slice(0, 3).toUpperCase() ?? 'KHO')
                                }}-{{ bulkReceiveLines.length }}
                            </div>
                        </div>

                        <!-- 1. THÔNG TIN CHUNG -->
                        <div class="mt-3 border border-black p-2">
                            <p class="font-bold uppercase text-[10px] text-neutral-800">1. THÔNG TIN CHUNG</p>
                            <div class="mt-1 grid grid-cols-2 gap-x-4 gap-y-1">
                                <p><b>Ngày nhận:</b> {{ bulkToday.day }}/{{ bulkToday.month }}/{{ bulkToday.year }}</p>
                                <p><b>Giờ nhận:</b> {{ bulkToday.time }}</p>
                                <div class="col-span-2">
                                    <b>Căn cứ:</b>
                                    <span class="font-bold ml-1">[x] Phiếu điều chuyển số: DC-NL/{{ bulkToday.year }}/...</span>
                                    <span class="ml-2 font-mono">(Mã GN: {{ bulkReceiveHandoverCode }})</span>
                                </div>
                                <p><b>Người lập phiếu:</b> {{ bulkReceiveTargetGroup?.requested_by || 'Thủ kho nhận' }}</p>
                                <p><b>Chức vụ:</b> Quản lý chi nhánh / Thủ kho</p>
                            </div>
                        </div>

                        <!-- 2. & 3. THÔNG TIN BÊN GIAO / BÊN NHẬN -->
                        <div class="mt-2 grid grid-cols-2 gap-2">
                            <div class="border border-black p-2">
                                <p class="font-bold uppercase text-[10px] text-neutral-800">2. THÔNG TIN BÊN GIAO (NƠI XUẤT)</p>
                                <div class="mt-1 space-y-0.5">
                                    <p><b>Kho xuất:</b> {{ bulkReceiveLines[0]?.transfer.from_branch ?? 'Kho Tổng' }}</p>
                                    <p><b>Địa chỉ kho:</b> Khu vực kho chi nhánh xuất hàng</p>
                                    <p><b>Người giao hàng:</b> {{ bulkReceiveLines[0]?.transfer.dispatched_by || 'Nhân viên giao nhận' }}</p>
                                    <p><b>SĐT:</b> 024 1234 5678</p>
                                </div>
                            </div>
                            <div class="border border-black p-2">
                                <p class="font-bold uppercase text-[10px] text-neutral-800">3. THÔNG TIN BÊN NHẬN (NƠI NHẬN)</p>
                                <div class="mt-1 space-y-0.5">
                                    <p><b>Kho nhận:</b> {{ bulkReceiveLines[0]?.transfer.to_branch ?? '—' }}</p>
                                    <p><b>Địa chỉ kho:</b> Khu vực nhận hàng chi nhánh</p>
                                    <p><b>Người nhận hàng:</b> {{ bulkReceiveTargetGroup?.requested_by || 'Thủ kho nhận' }}</p>
                                    <p><b>SĐT:</b> 024 1234 5678</p>
                                </div>
                            </div>
                        </div>

                        <!-- 4. THÔNG TIN VẬN CHUYỂN -->
                        <div class="mt-2 border border-black p-2">
                            <p class="font-bold uppercase text-[10px] text-neutral-800">4. THÔNG TIN VẬN CHUYỂN</p>
                            <div class="mt-1 grid grid-cols-2 gap-x-4 gap-y-1">
                                <p><b>Phương tiện vận chuyển:</b> Xe máy / Xe tải nội bộ</p>
                                <p><b>Thời gian xuất phát:</b> {{ bulkToday.day }}/{{ bulkToday.month }}/{{ bulkToday.year }}</p>
                                <p><b>Biển số xe:</b> —</p>
                                <p><b>Thời gian thực tế đến:</b> {{ bulkToday.day }}/{{ bulkToday.month }}/{{ bulkToday.year }} {{ bulkToday.time }}</p>
                                <div class="col-span-2">
                                    <b>Đơn vị vận chuyển:</b>
                                    <span class="font-bold ml-1">[x] Tự vận chuyển</span>
                                    <span class="ml-3">[ ] Đơn vị vận chuyển ngoài</span>
                                </div>
                            </div>
                        </div>

                        <!-- 5. DANH SÁCH NGUYÊN LIỆU NHẬN -->
                        <div class="mt-2">
                            <p class="mb-1 font-bold uppercase text-[10px] text-neutral-800">5. DANH SÁCH NGUYÊN LIỆU NHẬN</p>
                            <table class="w-full border-collapse border border-black text-[10px]">
                                <thead>
                                    <tr class="bg-neutral-100 font-bold text-black">
                                        <th rowspan="2" class="border border-black px-1.5 py-1 text-center">STT</th>
                                        <th rowspan="2" class="border border-black px-1.5 py-1 text-left">Mã nguyên liệu</th>
                                        <th rowspan="2" class="border border-black px-1.5 py-1 text-left">Tên nguyên liệu</th>
                                        <th rowspan="2" class="border border-black px-1.5 py-1 text-center">Đơn vị tính</th>
                                        <th colspan="3" class="border border-black px-1.5 py-1 text-center">Số lượng</th>
                                        <th rowspan="2" class="border border-black px-1.5 py-1 text-right">Đơn giá (VND)</th>
                                        <th rowspan="2" class="border border-black px-1.5 py-1 text-right">Thành tiền (VND)</th>
                                        <th rowspan="2" class="border border-black px-1.5 py-1 text-left">Ghi chú</th>
                                    </tr>
                                    <tr class="bg-neutral-100 font-bold text-black">
                                        <th class="border border-black px-1.5 py-0.5 text-center">Theo phiếu ĐC</th>
                                        <th class="border border-black px-1.5 py-0.5 text-center">Nhận thực tế</th>
                                        <th class="border border-black px-1.5 py-0.5 text-center">Chênh lệch (+/-)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="(line, i) in bulkReceiveLines"
                                        :key="line.transfer.id"
                                    >
                                        <td class="border border-black px-1.5 py-1 text-center">{{ i + 1 }}</td>
                                        <td class="border border-black px-1.5 py-1 font-mono">TR-{{ String(line.transfer.id).padStart(5, '0') }}</td>
                                        <td class="border border-black px-1.5 py-1 font-bold">{{ line.transfer.ingredient }}</td>
                                        <td class="border border-black px-1.5 py-1 text-center">{{ line.transfer.unit }}</td>
                                        <td class="border border-black px-1.5 py-1 text-center">{{ formatNumber(line.dispatchedQty) }}</td>
                                        <td class="border border-black px-1.5 py-1 text-center font-black text-emerald-800">{{ formatNumber(line.goodQty) }}</td>
                                        <td class="border border-black px-1.5 py-1 text-center font-semibold" :class="line.shortageQty > 0 || line.damagedQty > 0 ? 'text-rose-700' : ''">
                                            {{ line.shortageQty > 0 ? `-${formatNumber(line.shortageQty)}` : (line.damagedQty > 0 ? `Hỏng ${formatNumber(line.damagedQty)}` : '0') }}
                                        </td>
                                        <td class="border border-black px-1.5 py-1 text-right">{{ formatCurrency(line.transfer.source_unit_cost || 0) }}</td>
                                        <td class="border border-black px-1.5 py-1 text-right font-semibold">{{ formatCurrency((line.goodQty || 0) * (line.transfer.source_unit_cost || 0)) }}</td>
                                        <td class="border border-black px-1.5 py-1 text-neutral-600">
                                            <span v-if="line.damagedQty > 0">Cách ly {{ formatNumber(line.damagedQty) }} {{ line.transfer.unit }}</span>
                                            <span v-else-if="line.shortageQty > 0">Thiếu {{ formatNumber(line.shortageQty) }} {{ line.transfer.unit }}</span>
                                            <span v-else>Đủ</span>
                                        </td>
                                    </tr>
                                    <tr class="bg-neutral-100 font-bold">
                                        <td colspan="4" class="border border-black px-1.5 py-1 text-right uppercase">TỔNG CỘNG</td>
                                        <td class="border border-black px-1.5 py-1 text-center">{{ bulkReceiveLines.reduce((s, l) => s + l.dispatchedQty, 0).toFixed(3) }}</td>
                                        <td class="border border-black px-1.5 py-1 text-center font-black">{{ bulkReceiveLines.reduce((s, l) => s + (l.goodQty || 0), 0).toFixed(3) }}</td>
                                        <td class="border border-black px-1.5 py-1 text-center font-bold">
                                            {{ bulkReceiveLines.reduce((s, l) => s + l.shortageQty, 0) > 0 ? `-${bulkReceiveLines.reduce((s, l) => s + l.shortageQty, 0).toFixed(3)}` : '0' }}
                                        </td>
                                        <td class="border border-black px-1.5 py-1 text-right">—</td>
                                        <td class="border border-black px-1.5 py-1 text-right font-black">{{ formatCurrency(bulkReceiveTotalCost) }}</td>
                                        <td class="border border-black"></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- 6. KIỂM TRA CHẤT LƯỢNG -->
                        <div class="mt-2 border border-black p-2">
                            <p class="font-bold uppercase text-[10px] text-neutral-800">6. KIỂM TRA CHẤT LƯỢNG</p>
                            <div class="mt-1 space-y-1">
                                <div class="flex items-center gap-4">
                                    <b>Tình trạng nguyên liệu:</b>
                                    <span :class="!bulkReceiveHasDiscrepancy ? 'font-bold' : ''">[{{ !bulkReceiveHasDiscrepancy ? 'x' : ' ' }}] Đạt yêu cầu</span>
                                    <span :class="bulkReceiveHasDiscrepancy ? 'font-bold' : ''">[{{ bulkReceiveHasDiscrepancy ? 'x' : ' ' }}] Không đạt yêu cầu / Có hàng cách ly</span>
                                </div>
                                <div class="flex flex-wrap items-center gap-3 text-[10px]">
                                    <b>Chứng từ kèm theo:</b>
                                    <span>[x] Phiếu điều chuyển</span>
                                    <span>[x] Biên bản bàn giao</span>
                                    <span>[x] Ảnh/Minh chứng kiểm đếm ({{ bulkReceiveEvidenceFile?.name || 'Đã nộp' }})</span>
                                </div>
                                <p><b>Ghi chú chi tiết:</b> {{ bulkReceiveNote || 'Nguyên liệu đạt tiêu chuẩn vệ sinh an toàn thực phẩm, bao bì nguyên vẹn.' }}</p>
                            </div>
                        </div>

                        <!-- 7. KẾT LUẬN -->
                        <div class="mt-2 border border-black p-2">
                            <p class="font-bold uppercase text-[10px] text-neutral-800">7. KẾT LUẬN</p>
                            <p class="mt-0.5 text-[10px] italic">Chúng tôi xác nhận đã nhận đủ số lượng và chất lượng nguyên liệu theo Phiếu điều chuyển nêu trên.</p>
                            <div class="mt-1 flex items-center gap-4">
                                <b>Kết luận:</b>
                                <span :class="!bulkReceiveHasDiscrepancy ? 'font-bold' : ''">[{{ !bulkReceiveHasDiscrepancy ? 'x' : ' ' }}] Đúng theo phiếu điều chuyển</span>
                                <span :class="bulkReceiveHasDiscrepancy ? 'font-bold' : ''">[{{ bulkReceiveHasDiscrepancy ? 'x' : ' ' }}] Thiếu, hỏng cách ly (xem mục chênh lệch)</span>
                            </div>
                        </div>

                        <!-- 8. CHỮ KÝ XÁC NHẬN -->
                        <div class="mt-2">
                            <p class="mb-1 font-bold uppercase text-[10px] text-neutral-800">8. CHỮ KÝ XÁC NHẬN</p>
                            <div class="grid grid-cols-5 gap-1.5 text-center text-[9px]">
                                <div class="border border-black p-1.5">
                                    <p class="font-bold uppercase">NGƯỜI GIAO HÀNG</p>
                                    <p class="text-neutral-500">(Nơi xuất)</p>
                                    <div class="mt-7 border-t border-dotted border-neutral-400 pt-0.5 text-neutral-400">........................</div>
                                    <p class="mt-1 text-[8px]">Ngày {{ bulkToday.day }}/{{ bulkToday.month }}/{{ bulkToday.year }}</p>
                                </div>
                                <div class="border border-black p-1.5">
                                    <p class="font-bold uppercase">THỦ KHO XUẤT</p>
                                    <p class="text-neutral-500">(Nơi xuất)</p>
                                    <div class="mt-7 border-t border-dotted border-neutral-400 pt-0.5 text-neutral-400">........................</div>
                                    <p class="mt-1 text-[8px]">Ngày {{ bulkToday.day }}/{{ bulkToday.month }}/{{ bulkToday.year }}</p>
                                </div>
                                <div class="border border-black p-1.5">
                                    <p class="font-bold uppercase">NGƯỜI NHẬN HÀNG</p>
                                    <p class="text-neutral-500">(Nơi nhận)</p>
                                    <div class="mt-7 border-t border-dotted border-neutral-400 pt-0.5 text-neutral-400">........................</div>
                                    <p class="mt-1 text-[8px]">Ngày {{ bulkToday.day }}/{{ bulkToday.month }}/{{ bulkToday.year }}</p>
                                </div>
                                <div class="border border-black p-1.5">
                                    <p class="font-bold uppercase">THỦ KHO NHẬN</p>
                                    <p class="text-neutral-500">(Nơi nhận)</p>
                                    <div class="mt-7 border-t border-dotted border-neutral-400 pt-0.5 text-neutral-400">........................</div>
                                    <p class="mt-1 text-[8px]">Ngày {{ bulkToday.day }}/{{ bulkToday.month }}/{{ bulkToday.year }}</p>
                                </div>
                                <div class="border border-black p-1.5">
                                    <p class="font-bold uppercase">QC / KIỂM SOÁT</p>
                                    <p class="text-neutral-500">(Nơi nhận)</p>
                                    <div class="mt-7 border-t border-dotted border-neutral-400 pt-0.5 text-neutral-400">........................</div>
                                    <p class="mt-1 text-[8px]">Ngày {{ bulkToday.day }}/{{ bulkToday.month }}/{{ bulkToday.year }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Footer notes -->
                        <div class="mt-3 border-t border-neutral-300 pt-1 text-[9px] text-neutral-600">
                            <p>* Phiếu nhận nguyên liệu điều chuyển được lập 02 bản: 01 bản lưu tại kho nhận, 01 bản gửi về kho xuất.</p>
                            <p>* Liên: 1. Lưu tại Kho nhận; 2. Gửi về Kho xuất; 3. Bộ phận Kế toán (nếu cần).</p>
                        </div>
                    </div>

                    <!-- actions -->
                    <div class="mt-5 flex items-center justify-between gap-3">
                        <div v-if="bulkReceiveSubmitting" class="text-sm font-semibold text-emerald-400">
                            Đang nhận {{ bulkReceiveDone }}/{{ bulkReceiveLines.length }} nguyên liệu...
                        </div>
                        <div v-else class="text-xs text-muted-foreground">
                            Sau khi xác nhận, hệ thống sẽ nhập kho lần lượt <b class="text-emerald-400">{{ bulkReceiveLines.length }}</b> nguyên liệu.
                        </div>
                        <div class="flex gap-2">
                            <Button variant="outline" @click="goBackToBulkReceiveForm" :disabled="bulkReceiveSubmitting">← Quay lại</Button>
                            <Button
                                class="gap-2 bg-emerald-600 font-bold text-white hover:bg-emerald-700"
                                :disabled="bulkReceiveSubmitting"
                                @click="submitBulkReceive"
                                id="bulk-receive-confirm-btn"
                            >
                                <PackageCheck class="size-4" />
                                {{ bulkReceiveSubmitting ? 'Đang xử lý...' : 'Xác nhận nhập kho tất cả' }}
                            </Button>
                        </div>
                    </div>
                </template>
            </div>
        </div>
        <!-- ════ end bulk receive modal ════ -->

        <div
            v-if="
                showRequest ||
                routing ||
                batchRoutingGroup ||
                batchRejectingGroup ||
                batchCancellingGroup ||
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
                :class="detailTransfer || showRequest ? 'max-w-2xl' : 'max-w-lg'"
            >
                <template v-if="showRequest">
                    <div class="mb-5 flex items-start justify-between gap-3">
                        <div>
                            <p
                                class="text-[10px] font-bold tracking-wider text-teal-400 uppercase"
                            >
                                Khởi tạo điều chuyển
                            </p>
                            <h2 class="mt-1 text-xl font-black">
                                Tạo yêu cầu điều chuyển
                            </h2>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{
                                    requestOnly
                                        ? 'Yêu cầu được gửi tới Chủ doanh nghiệp để xem xét và điều phối; chưa thay đổi tồn kho.'
                                        : 'Yêu cầu sẽ nằm ở trạng thái chờ định tuyến cho đến khi kho cấp được chọn.'
                                }}
                            </p>
                        </div>
                        <Button variant="ghost" size="icon" @click="closeModals"
                            ><X class="size-4"
                        /></Button>
                    </div>
                    <form @submit.prevent="submitRequest" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="flex flex-col gap-1.5">
                                <Label>Chi nhánh cần hàng <span class="text-rose-500">*</span></Label>
                                <select
                                    v-model="requestForm.to_branch_id"
                                    required
                                    :disabled="isBranchScoped"
                                    class="h-10 rounded-md border border-input bg-background px-3 text-sm text-foreground disabled:opacity-85 disabled:cursor-not-allowed disabled:bg-muted/40 font-medium"
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
                                <div class="flex items-center justify-between">
                                    <Label>Kho cấp hàng (Trực tiếp)</Label>
                                    <span class="text-[10px] text-teal-400 font-bold uppercase tracking-wider">Tùy chọn</span>
                                </div>
                                <select
                                    v-model="requestForm.from_branch_id"
                                    class="h-10 rounded-md border border-input bg-background px-3 text-sm text-foreground"
                                >
                                    <option :value="null">
                                        — Để Chủ / Trưởng kho định tuyến —
                                    </option>
                                    <option
                                        v-for="branch in props.branches.filter(b => b.id !== requestForm.to_branch_id)"
                                        :key="branch.id"
                                        :value="branch.id"
                                    >
                                        {{ branch.name }}
                                    </option>
                                </select>
                                <p
                                    v-if="requestForm.errors.from_branch_id"
                                    class="text-xs text-rose-500"
                                >
                                    {{ requestForm.errors.from_branch_id }}
                                </p>
                            </div>
                        </div>

                        <div class="rounded-lg border border-border/60 bg-muted/30 p-2.5 text-xs text-muted-foreground">
                            <span v-if="requestForm.from_branch_id" class="text-emerald-400 font-medium flex items-center gap-1.5">
                                ⚡ <span><strong>Gửi trực tiếp:</strong> Đơn sẽ chuyển thẳng đến kho cấp để họ chuẩn bị xuất hàng ngay mà không cần qua duyệt định tuyến.</span>
                            </span>
                            <span v-else class="flex items-center gap-1.5">
                                💡 <span><strong>Hàng chờ điều phối:</strong> Nếu không chọn trước kho cấp, đơn sẽ chuyển đến Chủ nhà hàng / Trưởng kho Tổng để chọn nguồn cấp phù hợp.</span>
                            </span>
                        </div>

                        <!-- Multi-item ingredients list -->
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <Label
                                    class="text-xs font-bold uppercase tracking-wider text-foreground"
                                >
                                    Danh sách nguyên liệu cần yêu cầu
                                    <span class="text-rose-500">*</span>
                                </Label>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    class="h-8 gap-1 border-teal-500/30 text-teal-400 hover:bg-teal-500/10 hover:text-teal-300"
                                    @click="addRequestLine"
                                >
                                    <Plus class="size-3.5" /> Thêm nguyên liệu
                                </Button>
                            </div>

                            <div class="space-y-2.5">
                                <div
                                    v-for="(line, index) in requestForm.items"
                                    :key="index"
                                    class="grid grid-cols-12 items-start gap-2 rounded-xl border border-border/80 bg-muted/20 p-2.5"
                                >
                                    <div class="col-span-7 flex flex-col gap-1">
                                        <select
                                            v-model="line.ingredient_id"
                                            required
                                            class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm text-foreground"
                                        >
                                            <option value="" disabled>
                                                — Chọn nguyên liệu —
                                            </option>
                                            <option
                                                v-for="ingredient in availableIngredientsForLine(
                                                    Number(index),
                                                )"
                                                :key="ingredient.id"
                                                :value="ingredient.id"
                                            >
                                                {{ ingredient.name }}
                                            </option>
                                        </select>
                                        <p
                                            v-if="
                                                requestForm.errors[
                                                    `items.${index}.ingredient_id`
                                                ]
                                            "
                                            class="text-xs text-rose-500"
                                        >
                                            {{
                                                requestForm.errors[
                                                    `items.${index}.ingredient_id`
                                                ]
                                            }}
                                        </p>
                                    </div>

                                    <div class="col-span-4 flex flex-col gap-1">
                                        <div class="relative">
                                            <Input
                                                v-model="line.quantity_requested"
                                                type="number"
                                                step="0.001"
                                                min="0.001"
                                                required
                                                placeholder="Số lượng"
                                                class="h-10 pr-14 text-sm"
                                            />
                                            <span
                                                class="pointer-events-none absolute inset-y-0 right-2.5 flex items-center text-xs font-semibold text-muted-foreground"
                                            >
                                                {{
                                                    selectedIngredientForLine(
                                                        Number(index),
                                                    )?.unit || 'ĐV'
                                                }}
                                            </span>
                                        </div>
                                        <p
                                            v-if="
                                                requestForm.errors[
                                                    `items.${index}.quantity_requested`
                                                ]
                                            "
                                            class="text-xs text-rose-500"
                                        >
                                            {{
                                                requestForm.errors[
                                                    `items.${index}.quantity_requested`
                                                ]
                                            }}
                                        </p>
                                    </div>

                                    <div
                                        class="col-span-1 flex h-10 items-center justify-center"
                                    >
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            class="size-8 text-muted-foreground hover:bg-rose-500/10 hover:text-rose-400"
                                            :disabled="
                                                requestForm.items.length === 1
                                            "
                                            @click="removeRequestLine(Number(index))"
                                        >
                                            <X class="size-4" />
                                        </Button>
                                    </div>
                                </div>
                            </div>
                            <p
                                v-if="requestForm.errors.items"
                                class="text-xs text-rose-500"
                            >
                                {{ requestForm.errors.items }}
                            </p>
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <Label>Mức độ yêu cầu</Label>
                            <select
                                v-model="requestForm.priority"
                                required
                                class="h-10 rounded-md border border-input bg-background px-3 text-sm text-foreground"
                            >
                                <option value="urgent">
                                    Khẩn cấp — cần bổ sung sớm
                                </option>
                                <option value="normal">Thông thường</option>
                            </select>
                            <p class="text-[11px] text-muted-foreground">
                                Yêu cầu khẩn cấp sẽ được đánh dấu ưu tiên trên toàn hệ thống.
                            </p>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <Label>Lý do / nhu cầu vận hành</Label>
                            <Input
                                v-model="requestForm.reason"
                                required
                                placeholder="VD: Thiếu hàng phục vụ ca tối, mượn tạm..."
                            />
                            <p
                                v-if="requestForm.errors.reason"
                                class="text-xs text-rose-500"
                            >
                                {{ requestForm.errors.reason }}
                            </p>
                        </div>
                        <div class="flex justify-end gap-2 pt-2">
                            <Button
                                type="button"
                                variant="outline"
                                @click="closeModals"
                                >Hủy</Button
                            >
                            <Button
                                type="submit"
                                :disabled="requestForm.processing"
                                class="bg-teal-600 font-bold text-white hover:bg-teal-500"
                            >
                                Gửi yêu cầu
                            </Button>
                        </div>
                    </form>
                </template>
                <template v-else-if="detailTransfer">
                    <div class="mb-5 flex items-start justify-between gap-3">
                        <div>
                            <p
                                class="text-[10px] font-bold tracking-wider text-teal-400 uppercase"
                            >
                                Hồ sơ điều chuyển
                            </p>
                            <div class="mt-1 flex flex-wrap items-center gap-2">
                                <h2 class="text-xl font-black">
                                    TR-{{
                                        String(detailTransfer.id).padStart(
                                            5,
                                            '0',
                                        )
                                    }}
                                </h2>
                                <span
                                    class="rounded-full border px-2 py-1 text-[10px] font-bold"
                                    :class="
                                        statusConfig[detailTransfer.status]
                                            .className
                                    "
                                >
                                    {{ statusLabel(detailTransfer.status) }}
                                </span>
                                <span
                                    v-if="isOverdue(detailTransfer)"
                                    class="rounded-full bg-rose-500/10 px-2 py-1 text-[10px] font-bold text-rose-300"
                                    >Quá SLA
                                    {{ overdueHours(detailTransfer) }}h</span
                                >
                            </div>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ detailTransfer.ingredient }} ·
                                {{
                                    detailTransfer.from_branch ||
                                    'Chưa chọn nguồn'
                                }}
                                → {{ detailTransfer.to_branch }}
                            </p>
                        </div>
                        <Button variant="ghost" size="icon" @click="closeModals"
                            ><X class="size-4"
                        /></Button>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-4">
                        <div class="rounded-xl bg-muted/40 p-3">
                            <p
                                class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                            >
                                Yêu cầu
                            </p>
                            <p class="mt-1 font-black text-foreground">
                                {{
                                    formatNumber(
                                        detailTransfer.quantity_requested,
                                    )
                                }}
                                {{ detailTransfer.unit }}
                            </p>
                        </div>
                        <div class="rounded-xl bg-muted/40 p-3">
                            <p
                                class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                            >
                                Đã xuất
                            </p>
                            <p class="mt-1 font-black text-foreground">
                                {{
                                    formatNumber(
                                        detailTransfer.quantity_dispatched,
                                    )
                                }}
                                {{ detailTransfer.unit }}
                            </p>
                        </div>
                        <div class="rounded-xl bg-muted/40 p-3">
                            <p
                                class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                            >
                                Thực nhận
                            </p>
                            <p class="mt-1 font-black text-foreground">
                                {{
                                    formatNumber(
                                        detailTransfer.quantity_received,
                                    )
                                }}
                                {{ detailTransfer.unit }}
                            </p>
                        </div>
                        <div class="rounded-xl bg-muted/40 p-3">
                            <p
                                class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                            >
                                Giá trị tạm tính
                            </p>
                            <p class="mt-1 font-black text-foreground">
                                {{
                                    formatCurrency(
                                        (detailTransfer.quantity_dispatched ??
                                            detailTransfer.quantity_requested) *
                                            detailTransfer.source_unit_cost,
                                    )
                                }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-5 rounded-2xl border border-border/80 p-4">
                        <div class="flex items-center gap-2">
                            <Activity class="size-4 text-teal-400" />
                            <h3 class="text-sm font-black text-foreground">
                                Tiến trình & trách nhiệm
                            </h3>
                        </div>
                        <div class="mt-4 grid gap-3 sm:grid-cols-4">
                            <div
                                v-for="step in timelineFor(detailTransfer)"
                                :key="step.label"
                                class="relative rounded-xl border p-3"
                                :class="
                                    step.done
                                        ? 'border-teal-400/25 bg-teal-400/5'
                                        : 'border-border/60 bg-muted/20 opacity-60'
                                "
                            >
                                <div class="flex items-center gap-2">
                                    <CheckCircle2
                                        v-if="step.done"
                                        class="size-4 text-teal-400"
                                    />
                                    <Clock3
                                        v-else
                                        class="size-4 text-muted-foreground"
                                    />
                                    <span
                                        class="text-xs font-bold text-foreground"
                                        >{{ step.label }}</span
                                    >
                                </div>
                                <p
                                    class="mt-2 text-[11px] text-muted-foreground"
                                >
                                    {{ step.at || 'Chưa thực hiện' }}
                                </p>
                                <p
                                    v-if="step.by"
                                    class="mt-1 truncate text-[11px] text-muted-foreground"
                                >
                                    {{ step.by }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div class="rounded-xl border border-border/80 p-4">
                            <h3
                                class="text-xs font-black tracking-wider text-muted-foreground uppercase"
                            >
                                Nội dung yêu cầu
                            </h3>
                            <p class="mt-2 text-sm text-foreground">
                                {{ detailTransfer.reason }}
                            </p>
                            <p class="mt-2 text-xs text-muted-foreground">
                                Người yêu cầu:
                                {{ detailTransfer.requested_by || '—' }}
                            </p>
                            <p
                                v-if="detailTransfer.owner_note"
                                class="mt-2 text-xs text-muted-foreground"
                            >
                                <b>Điều phối:</b>
                                {{ detailTransfer.owner_note }}
                            </p>
                        </div>
                        <div class="rounded-xl border border-border/80 p-4">
                            <h3
                                class="text-xs font-black tracking-wider text-muted-foreground uppercase"
                            >
                                Bàn giao & đối soát
                            </h3>
                            <p
                                v-if="detailTransfer.handover_code"
                                class="mt-2 text-sm text-foreground"
                            >
                                Mã giao nhận:
                                <strong
                                    class="font-mono tracking-[0.2em] text-violet-300"
                                    >{{ detailTransfer.handover_code }}</strong
                                >
                            </p>
                            <p
                                v-if="detailTransfer.received_condition"
                                class="mt-2 text-xs text-muted-foreground"
                            >
                                Tình trạng:
                                {{ detailTransfer.received_condition }}
                            </p>
                            <p
                                v-if="detailTransfer.received_note"
                                class="mt-2 text-xs text-muted-foreground"
                            >
                                Biên bản: {{ detailTransfer.received_note }}
                            </p>
                            <p
                                v-if="detailTransfer.discrepancy_quantity > 0"
                                class="mt-2 text-xs font-semibold text-rose-300"
                            >
                                Thiếu
                                {{
                                    formatNumber(
                                        detailTransfer.discrepancy_quantity,
                                    )
                                }}
                                {{ detailTransfer.unit }}
                            </p>
                            <a
                                v-if="detailTransfer.receiving_evidence_path"
                                :href="`/secure-files/download?path=${encodeURIComponent(detailTransfer.receiving_evidence_path)}`"
                                target="_blank"
                                rel="noreferrer"
                                class="mt-2 inline-flex items-center gap-1 text-xs font-bold text-teal-400"
                                ><FileText class="size-3.5" /> Mở bằng chứng</a
                            >
                            <p
                                v-if="detailTransfer.discrepancy_resolution"
                                class="mt-2 text-xs text-muted-foreground"
                            >
                                <b>Đã chốt:</b>
                                {{ detailTransfer.discrepancy_resolution }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-5 flex flex-wrap justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            @click="closeModals"
                            >Đóng</Button
                        >
                        <Button
                            v-if="detailTransfer.can_route"
                            type="button"
                            class="gap-1.5 bg-indigo-600 font-bold text-white hover:bg-indigo-700"
                            @click="openRoute(detailTransfer)"
                            ><RouteIcon class="size-3.5" /> Định tuyến</Button
                        >
                        <Button
                            v-if="detailTransfer.can_dispatch"
                            type="button"
                            class="gap-1.5 bg-amber-600 font-bold text-white hover:bg-amber-700"
                            @click="openDispatch(detailTransfer)"
                            ><PackageOpen class="size-3.5" /> Xuất kho</Button
                        >
                        <Button
                            v-if="detailTransfer.can_receive"
                            type="button"
                            class="gap-1.5 bg-emerald-600 font-bold text-white hover:bg-emerald-700"
                            @click="openReceive(detailTransfer)"
                            ><PackageCheck class="size-3.5" /> Nhận hàng</Button
                        >
                        <Button
                            v-if="detailTransfer.can_resolve"
                            type="button"
                            class="gap-1.5 bg-rose-600 font-bold text-white hover:bg-rose-700"
                            @click="openResolve(detailTransfer)"
                            ><ClipboardCheck class="size-3.5" /> Chốt chênh
                            lệch</Button
                        >
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
                                    v-for="branch in routeBranchOptions"
                                    :key="branch.id"
                                    :value="branch.id"
                                    :disabled="
                                        branch.available_quantity <
                                        routing.quantity_requested
                                    "
                                >
                                    {{ branch.name }} · tồn
                                    {{
                                        formatNumber(branch.available_quantity)
                                    }}
                                    {{ routing.unit }}
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
                        v-if="Number(dispatching.source_available_quantity || 0) < Number(dispatching.quantity_requested || 0)"
                        class="mb-4 rounded-xl border border-amber-500/30 bg-amber-500/10 p-3.5 text-xs text-amber-200"
                    >
                        <div class="flex items-center gap-1.5 font-bold text-amber-300">
                            <Info class="size-4 shrink-0" />
                            <span>Quy tắc chia sẻ kho (Tối đa 2/3 tồn kho)</span>
                        </div>
                        <div class="mt-2 space-y-1.5 text-amber-100/90">
                            <p>
                                • Đơn yêu cầu: <b>{{ formatNumber(dispatching.quantity_requested) }} {{ dispatching.unit }}</b>
                                &nbsp;|&nbsp;
                                Tồn hiện có: <b>{{ formatNumber(dispatching.source_available_quantity) }} {{ dispatching.unit }}</b>
                            </p>
                            <p class="rounded-lg bg-amber-950/40 p-2 text-amber-200">
                                💡 Do kho nguồn không đủ số lượng yêu cầu, hệ thống cho phép xuất tối đa <b>2/3 tồn kho ({{ formatNumber(getDispatchMaxAllowed(dispatching)) }} {{ dispatching.unit }})</b> để vừa hỗ trợ điều chuyển, vừa giữ lại 1/3 ({{ formatNumber(Math.max(0, Number((Number(dispatching.source_available_quantity) - getDispatchMaxAllowed(dispatching)).toFixed(3)))) }} {{ dispatching.unit }}) phục vụ vận hành tại chỗ.
                            </p>
                        </div>
                    </div>
                    <div
                        v-else
                        class="mb-4 rounded-xl border border-emerald-500/25 bg-emerald-950/20 p-3 text-xs text-emerald-100"
                    >
                        <p>
                            Tồn hiện tại kho nguồn:
                            <b class="text-emerald-300">{{ formatNumber(dispatching.source_available_quantity) }} {{ dispatching.unit }}</b>
                            (Đủ đáp ứng đơn yêu cầu {{ formatNumber(dispatching.quantity_requested) }} {{ dispatching.unit }}).
                        </p>
                    </div>
                    <form @submit.prevent="submitDispatch" class="space-y-4">
                        <div class="flex flex-col gap-1.5">
                            <div class="flex items-center justify-between">
                                <Label>Số lượng xuất</Label>
                                <span class="text-xs text-muted-foreground">
                                    Tối đa: <b class="text-amber-400">{{ formatNumber(getDispatchMaxAllowed(dispatching)) }} {{ dispatching.unit }}</b>
                                </span>
                            </div>
                            <Input
                                v-model="dispatchForm.quantity_dispatched"
                                type="number"
                                step="0.001"
                                min="0.001"
                                :max="getDispatchMaxAllowed(dispatching)"
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
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div class="flex flex-col gap-1.5 sm:col-span-2">
                                <Label class="text-xs font-bold text-foreground"
                                    >Mã giao nhận bàn giao
                                    <span class="text-rose-500">*</span></Label
                                >
                                <Input
                                    v-model="receiveForm.handover_code"
                                    class="font-mono tracking-[0.2em] uppercase"
                                    maxlength="6"
                                    required
                                    placeholder="Nhập mã 6 ký tự (VD: ABC123)"
                                />
                            </div>

                            <div class="flex flex-col gap-1 pt-1 sm:col-span-2">
                                <Label
                                    class="text-xs font-black tracking-wider text-emerald-400 uppercase"
                                    >Phân loại kiểm đếm thực nhận</Label
                                >
                                <p class="text-[11px] text-muted-foreground">
                                    Nhập số lượng thực tế đếm được theo từng
                                    tình trạng hàng (Số lượng đã xuất:
                                    {{ receiving.quantity_dispatched }}
                                    {{ receiving.unit }})
                                </p>
                            </div>

                            <div class="flex flex-col gap-1.5">
                                <Label
                                    class="text-xs font-bold text-emerald-400"
                                    >✅ Đạt chất lượng ({{ receiving.unit }})
                                    <span class="text-rose-500">*</span></Label
                                >
                                <Input
                                    v-model="receiveForm.quantity_received_good"
                                    type="number"
                                    step="0.001"
                                    min="0"
                                    :max="receiving.quantity_dispatched ?? 0"
                                    required
                                    placeholder="Nhập số lượng đạt"
                                />
                            </div>

                            <div class="flex flex-col gap-1.5">
                                <Label class="text-xs font-bold text-amber-400"
                                    >⚠️ Hàng hư hỏng / vỡ ({{
                                        receiving.unit
                                    }})</Label
                                >
                                <Input
                                    v-model="
                                        receiveForm.quantity_received_damaged
                                    "
                                    type="number"
                                    step="0.001"
                                    min="0"
                                    :max="receiving.quantity_dispatched ?? 0"
                                    placeholder="0 (hoặc số hỏng)"
                                />
                            </div>

                            <div class="flex flex-col gap-1.5 sm:col-span-2">
                                <Label class="text-xs font-bold text-rose-400"
                                    >❌ Hàng hết hạn / kém chất lượng ({{
                                        receiving.unit
                                    }})</Label
                                >
                                <Input
                                    v-model="
                                        receiveForm.quantity_received_expired
                                    "
                                    type="number"
                                    step="0.001"
                                    min="0"
                                    :max="receiving.quantity_dispatched ?? 0"
                                    placeholder="0 (hoặc số hết hạn)"
                                />
                            </div>

                            <div
                                class="flex items-center justify-between rounded-xl border border-emerald-400/20 bg-emerald-950/20 p-2.5 sm:col-span-2"
                            >
                                <span class="text-xs font-bold text-foreground"
                                    >Tổng số lượng thực nhận:</span
                                >
                                <span
                                    class="font-mono text-sm font-black text-emerald-300"
                                >
                                    {{
                                        Number(
                                            receiveForm.quantity_received_good ||
                                                0,
                                        ) +
                                        Number(
                                            receiveForm.quantity_received_damaged ||
                                                0,
                                        ) +
                                        Number(
                                            receiveForm.quantity_received_expired ||
                                                0,
                                        )
                                    }}
                                    {{ receiving.unit }}
                                </span>
                            </div>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <Label>Tình trạng hàng</Label
                            ><select
                                v-model="receiveForm.received_condition"
                                class="h-10 rounded-md border border-input bg-background px-3 text-sm"
                            >
                                <option value="good">
                                    Đủ và đạt chất lượng
                                </option>
                                <option value="shortage">Thiếu số lượng</option>
                                <option value="damaged">Hư hỏng</option>
                                <option value="mixed">
                                    Vừa thiếu vừa hư hỏng
                                </option>
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
                        <div class="grid grid-cols-2 gap-3">
                            <Input
                                v-model="
                                    receiveForm.transport_temperature_min_c
                                "
                                type="number"
                                step="0.1"
                                placeholder="Nhiệt độ thấp nhất (°C)"
                            />
                            <Input
                                v-model="
                                    receiveForm.transport_temperature_max_c
                                "
                                type="number"
                                step="0.1"
                                placeholder="Nhiệt độ cao nhất (°C)"
                            />
                            <Input
                                v-model="receiveForm.vehicle_number"
                                placeholder="Biển số xe"
                            />
                            <Input
                                v-model="receiveForm.carrier_name"
                                placeholder="Đơn vị vận chuyển"
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
                            <h2 class="mt-1 text-xl font-black">
                                Chốt chênh lệch
                            </h2>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Thiếu {{ resolving.discrepancy_quantity }}
                                {{ resolving.unit }} ·
                                {{ resolving.ingredient }}
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
                            Hàng thực nhận đã được cộng vào kho đích. Việc chốt
                            này xác nhận hướng xử lý phần thiếu/hỏng và đóng hồ
                            sơ điều chuyển.
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
                                {{
                                    cancelling
                                        ? 'Hủy yêu cầu'
                                        : 'Từ chối yêu cầu'
                                }}
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
                    <form
                        v-else
                        @submit.prevent="submitReject"
                        class="space-y-4"
                    >
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

                <template v-else-if="batchRoutingGroup">
                    <div class="mb-5 flex items-start justify-between gap-3">
                        <div>
                            <p
                                class="text-[10px] font-bold tracking-wider text-indigo-400 uppercase"
                            >
                                Bước 1 · Định tuyến cả phiếu
                            </p>
                            <h2 class="mt-1 text-xl font-black">
                                Chọn kho cấp cho cả phiếu ({{ batchRoutingGroup.items.length }} nguyên liệu)
                            </h2>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Cấp về: <strong class="text-foreground">{{ batchRoutingGroup.to_branch }}</strong> · Người yêu cầu: {{ batchRoutingGroup.requested_by || '—' }}
                            </p>
                        </div>
                        <Button variant="ghost" size="icon" @click="closeModals"
                            ><X class="size-4"
                        /></Button>
                    </div>
                    <form @submit.prevent="submitBatchRoute" class="space-y-4">
                        <div class="flex flex-col gap-1.5">
                            <Label>Chi nhánh / Kho cấp hàng</Label>
                            <select
                                v-model="batchRouteForm.from_branch_id"
                                required
                                class="h-10 rounded-md border border-input bg-background px-3 text-sm"
                            >
                                <option value="" disabled>
                                    — Chọn kho nguồn cấp —
                                </option>
                                <option
                                    v-for="branch in batchRouteBranchOptions"
                                    :key="branch.id"
                                    :value="branch.id"
                                >
                                    {{ branch.name }}
                                </option>
                            </select>
                            <p
                                v-if="batchRouteForm.errors.from_branch_id"
                                class="text-xs text-rose-500"
                            >
                                {{ batchRouteForm.errors.from_branch_id }}
                            </p>
                        </div>

                        <div class="rounded-xl border border-border/80 bg-muted/20 p-3">
                            <p class="mb-2 text-xs font-bold text-foreground">
                                Danh sách nguyên liệu trong phiếu:
                            </p>
                            <div class="max-h-56 divide-y divide-border/60 overflow-y-auto pr-1">
                                <div
                                    v-for="item in batchRoutingGroup.items"
                                    :key="item.id"
                                    class="flex items-center justify-between gap-2 py-2 text-xs"
                                >
                                    <div class="min-w-0">
                                        <p class="truncate font-bold text-foreground">{{ item.ingredient }}</p>
                                        <p class="text-[11px] text-muted-foreground">
                                            Cần: {{ formatNumber(item.quantity_requested) }} {{ item.unit }}
                                        </p>
                                    </div>
                                    <div class="shrink-0 text-right">
                                        <template v-if="batchRouteForm.from_branch_id">
                                            <span
                                                v-if="getBranchStockForIngredient(batchRouteForm.from_branch_id, item.ingredient_id) + 0.0005 >= item.quantity_requested"
                                                class="inline-flex items-center gap-1 font-semibold text-emerald-400"
                                            >
                                                <Check class="size-3" /> Tồn {{ formatNumber(getBranchStockForIngredient(batchRouteForm.from_branch_id, item.ingredient_id)) }} {{ item.unit }}
                                            </span>
                                            <span v-else class="inline-flex items-center gap-1 font-bold text-rose-400">
                                                <AlertTriangle class="size-3" /> Thiếu tồn (còn {{ formatNumber(getBranchStockForIngredient(batchRouteForm.from_branch_id, item.ingredient_id)) }} {{ item.unit }})
                                            </span>
                                        </template>
                                        <span v-else class="text-muted-foreground">—</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div
                            v-if="hasShortageInBatchRoute"
                            class="flex items-start gap-2 rounded-xl border border-rose-500/20 bg-rose-500/10 p-3 text-xs text-rose-300"
                        >
                            <AlertTriangle class="mt-0.5 size-4 shrink-0" />
                            <p>Kho nguồn đã chọn không đủ tồn cho một số nguyên liệu trong phiếu. Hãy chọn kho nguồn khác hoặc điều chuyển lẻ từng món.</p>
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <Label>Ghi chú điều phối chung</Label>
                            <textarea
                                v-model="batchRouteForm.owner_note"
                                rows="3"
                                class="rounded-md border border-input bg-background px-3 py-2 text-sm"
                                placeholder="Ghi chú đóng gói, phương thức bàn giao cho toàn bộ phiếu..."
                            />
                        </div>
                        <div class="flex justify-end gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                @click="closeModals"
                                >Hủy</Button
                            >
                            <Button
                                type="submit"
                                :disabled="batchRouteForm.processing || !batchRouteForm.from_branch_id || hasShortageInBatchRoute"
                                class="bg-indigo-600 font-bold text-white hover:bg-indigo-700"
                            >
                                Duyệt & Định tuyến cả phiếu
                            </Button>
                        </div>
                    </form>
                </template>

                <template v-else-if="batchRejectingGroup">
                    <div class="mb-5 flex items-start justify-between gap-3">
                        <div>
                            <p
                                class="text-[10px] font-bold tracking-wider text-rose-400 uppercase"
                            >
                                Kiểm soát yêu cầu tổng
                            </p>
                            <h2 class="mt-1 text-xl font-black">
                                Từ chối cả phiếu ({{ batchRejectingGroup.items.length }} nguyên liệu)
                            </h2>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Chi nhánh: {{ batchRejectingGroup.to_branch }} · Người tạo: {{ batchRejectingGroup.requested_by || '—' }}
                            </p>
                        </div>
                        <Button variant="ghost" size="icon" @click="closeModals"
                            ><X class="size-4"
                        /></Button>
                    </div>
                    <form @submit.prevent="submitBatchReject" class="space-y-4">
                        <div class="flex flex-col gap-1.5">
                            <Label>Lý do từ chối cả phiếu</Label>
                            <textarea
                                v-model="batchRejectForm.reject_reason"
                                rows="4"
                                required
                                class="rounded-md border border-input bg-background px-3 py-2 text-sm"
                                placeholder="Ví dụ: Không có kho nào đủ điều kiện cấp hàng đợt này..."
                            />
                        </div>
                        <div class="flex justify-end gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                @click="closeModals"
                                >Quay lại</Button
                            >
                            <Button
                                type="submit"
                                :disabled="batchRejectForm.processing"
                                class="bg-rose-600 font-bold text-white hover:bg-rose-700"
                            >
                                Từ chối cả phiếu
                            </Button>
                        </div>
                    </form>
                </template>

                <!-- ─── Bulk Dispatch Modal panels (appended at end of modal switcher) ─── -->

                <template v-else-if="false"><!-- placeholder --></template>

                <template v-else-if="batchCancellingGroup">
                    <div class="mb-5 flex items-start justify-between gap-3">
                        <div>
                            <p
                                class="text-[10px] font-bold tracking-wider text-rose-400 uppercase"
                            >
                                Kiểm soát yêu cầu tổng
                            </p>
                            <h2 class="mt-1 text-xl font-black">
                                Hủy cả phiếu ({{ batchCancellingGroup.items.length }} nguyên liệu)
                            </h2>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Chi nhánh: {{ batchCancellingGroup.to_branch }} · Người tạo: {{ batchCancellingGroup.requested_by || '—' }}
                            </p>
                        </div>
                        <Button variant="ghost" size="icon" @click="closeModals"
                            ><X class="size-4"
                        /></Button>
                    </div>
                    <form @submit.prevent="submitBatchCancel" class="space-y-4">
                        <div class="flex flex-col gap-1.5">
                            <Label>Lý do hủy cả phiếu</Label>
                            <textarea
                                v-model="batchCancelForm.cancel_reason"
                                rows="4"
                                required
                                class="rounded-md border border-input bg-background px-3 py-2 text-sm"
                                placeholder="Ví dụ: Đã cân đối được tồn nội bộ, không cần điều chuyển..."
                            />
                        </div>
                        <div class="flex justify-end gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                @click="closeModals"
                                >Quay lại</Button
                            >
                            <Button
                                type="submit"
                                :disabled="batchCancelForm.processing"
                                class="bg-rose-600 font-bold text-white hover:bg-rose-700"
                            >
                                Xác nhận hủy cả phiếu
                            </Button>
                        </div>
                    </form>
                </template>
            </div>
        </div>
    </Teleport>
</template>
