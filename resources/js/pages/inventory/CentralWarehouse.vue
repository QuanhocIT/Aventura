<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import {
    Activity,
    AlertCircle,
    AlertTriangle,
    ArrowUpRight,
    BarChart3,
    Boxes,
    Building2,
    CalendarDays,
    Check,
    CheckCircle2,
    ClipboardList,
    Clock,
    DollarSign,
    Eye,
    FileDown,
    Lightbulb,
    MapPin,
    PackageCheck,
    Save,
    Search,
    Truck,
    TrendingUp,
    UserCheck,
    UserRound,
    Warehouse,
    X,
    XCircle,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
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
import WarehouseAiRecommendations from '@/components/WarehouseAiRecommendations.vue';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    centralBranch: any;
    branches: Array<any>;
    supplyRequests: Array<any>;
    ingredients: Array<any>;
    canManageWarehouse: boolean;
    canApproveRequests: boolean;
    canDispatchRequests: boolean;
    warehouseStaff: Array<any>;
    warehouseTasks: Array<any>;
    warehouseTaskSummary: any;
    supplyAnalytics: any;
    centralWarehouseAnalytics?: any;
    receivingVouchers?: Array<any>;
    receivingSummary?: any;
    inventorySummary?: any;
    warehouseLocations?: Array<any>;
    centralWarehouseAi?: any;
}>();

const activeTab = ref<
    | 'all'
    | 'pending'
    | 'approved'
    | 'preparing'
    | 'dispatch_pending_approval'
    | 'dispatched'
    | 'partial_received'
    | 'disputed'
    | 'exceptions'
    | 'completed'
    | 'task_board'
>('all');
const searchQuery = ref('');
const selectedBranchFilter = ref<number | string>('all');
const selectedRequest = ref<any>(null);
const isDetailModalOpen = ref(false);
const isPickingModalOpen = ref(false);
const pickingItems = ref<Array<any>>([]);
const isProcessing = ref(false);
const isSavingPrices = ref(false);
const priceRows = ref(
    props.ingredients.map((ing) => ({
        ingredient_id: ing.id,
        name: ing.name,
        sku: ing.sku,
        unit_symbol: ing.unit?.symbol || 'đv',
        average_cost: Number(ing.average_cost || 0),
    })),
);

const taskAssignments = ref<Array<any>>([...props.warehouseTasks]);
const isTaskModalOpen = ref(false);
const isTaskProcessing = ref(false);
const taskForm = ref({
    supply_request_id: '' as number | string,
    assigned_to: '' as number | string,
    task_type: 'picking',
    priority: 'normal',
    due_at: '',
    notes: '',
});

const taskSummary = computed(() => ({
    total: taskAssignments.value.length,
    assigned: taskAssignments.value.filter((task) => task.status === 'assigned')
        .length,
    in_progress: taskAssignments.value.filter(
        (task) => task.status === 'in_progress',
    ).length,
    completed: taskAssignments.value.filter(
        (task) => task.status === 'completed',
    ).length,
    unassigned: taskAssignments.value.filter(
        (task) => !task.assigned_to && task.status !== 'completed',
    ).length,
}));

const staffWorkload = computed(() =>
    props.warehouseStaff.map((staff) => ({
        ...staff,
        activeTasks: taskAssignments.value.filter(
            (task) =>
                task.assigned_to === staff.id &&
                ['assigned', 'in_progress'].includes(task.status),
        ).length,
        completedTasks: taskAssignments.value.filter(
            (task) =>
                task.assigned_to === staff.id && task.status === 'completed',
        ).length,
    })),
);

// Filtered Requests
const filteredRequests = computed(() => {
    return props.supplyRequests.filter((req) => {
        const matchesBranch =
            selectedBranchFilter.value === 'all' ||
            req.to_branch_id === Number(selectedBranchFilter.value);
        const matchesSearch =
            !searchQuery.value.trim() ||
            req.request_code
                .toLowerCase()
                .includes(searchQuery.value.toLowerCase()) ||
            req.to_branch?.name
                ?.toLowerCase()
                .includes(searchQuery.value.toLowerCase()) ||
            req.creator?.name
                ?.toLowerCase()
                .includes(searchQuery.value.toLowerCase());

        return matchesBranch && matchesSearch;
    });
});

// Stats Counters
const stats = computed(() => ({
    total: props.supplyRequests.length,
    pending: props.supplyRequests.filter((r) => r.status === 'pending').length,
    approved: props.supplyRequests.filter((r) => r.status === 'approved')
        .length,
    preparing: props.supplyRequests.filter((r) => r.status === 'preparing')
        .length,
    dispatch_pending: props.supplyRequests.filter(
        (r) =>
            r.status === 'dispatch_pending_approval' || r.status === 'prepared',
    ).length,
    dispatched: props.supplyRequests.filter((r) => r.status === 'dispatched')
        .length,
    partial_received: props.supplyRequests.filter(
        (r) => r.status === 'partial_received',
    ).length,
    disputed: props.supplyRequests.filter((r) => r.status === 'disputed')
        .length,
    completed: props.supplyRequests.filter((r) => r.status === 'completed')
        .length,
}));

const terminalStatuses = ['completed', 'cancelled', 'rejected'];
const openRequests = computed(() =>
    props.supplyRequests.filter(
        (request) => !terminalStatuses.includes(request.status),
    ),
);
const overdueRequests = computed(() =>
    openRequests.value.filter(
        (request) =>
            request.requested_delivery_date &&
            new Date(request.requested_delivery_date).getTime() < Date.now(),
    ),
);
const attentionRequests = computed(() =>
    [...openRequests.value]
        .sort((a, b) => {
            const overdueDelta = Number(isOverdue(b)) - Number(isOverdue(a));

            if (overdueDelta !== 0) {
                return overdueDelta;
            }

            const statusOrder: Record<string, number> = {
                pending: 1,
                dispatch_pending_approval: 2,
                approved: 3,
                preparing: 4,
                partial_received: 5,
                disputed: 6,
                dispatched: 7,
            };

            return (
                (statusOrder[a.status] ?? 99) - (statusOrder[b.status] ?? 99)
            );
        })
        .slice(0, 6),
);
const actionQueue = computed(() => [
    {
        key: 'pending' as const,
        label: 'Chờ duyệt đơn',
        description: 'Kiểm tra nhu cầu, hạn mức và tồn khả dụng',
        count: stats.value.pending,
        tone: 'amber',
        icon: Clock,
    },
    {
        key: 'approved' as const,
        label: 'Cần soạn hàng',
        description: 'Chọn lô FEFO và chốt số lượng thực xuất',
        count: stats.value.approved,
        tone: 'blue',
        icon: PackageCheck,
    },
    {
        key: 'dispatch_pending_approval' as const,
        label: 'Chờ duyệt xuất',
        description: 'Trưởng kho xác nhận lệnh xuất cuối cùng',
        count: stats.value.dispatch_pending,
        tone: 'violet',
        icon: ClipboardList,
    },
    {
        key: 'exceptions' as const,
        label: 'Theo dõi nhận hàng',
        description: 'Thiếu hàng hoặc phát sinh chênh lệch tại chi nhánh',
        count: stats.value.partial_received + stats.value.disputed,
        tone: 'orange',
        icon: AlertTriangle,
    },
]);
const pipelineStages = computed(() => [
    { key: 'pending' as const, label: 'Chờ duyệt', count: stats.value.pending },
    {
        key: 'approved' as const,
        label: 'Đã duyệt',
        count: stats.value.approved,
    },
    {
        key: 'preparing' as const,
        label: 'Đang soạn',
        count: stats.value.preparing,
    },
    {
        key: 'dispatch_pending_approval' as const,
        label: 'Duyệt xuất',
        count: stats.value.dispatch_pending,
    },
    {
        key: 'dispatched' as const,
        label: 'Đang giao',
        count: stats.value.dispatched,
    },
    {
        key: 'completed' as const,
        label: 'Hoàn thành',
        count: stats.value.completed,
    },
]);

const filterTabs = computed(() => [
    {
        key: 'all' as const,
        label: 'Tất cả',
        count: stats.value.total,
        activeClass:
            'bg-background text-foreground shadow-xs ring-1 ring-border/60 font-bold',
        activeBadge:
            'bg-slate-200 text-slate-800 dark:bg-slate-800 dark:text-slate-200 font-bold',
    },
    {
        key: 'pending' as const,
        label: 'Chờ duyệt',
        count: stats.value.pending,
        activeClass:
            'bg-amber-500/15 text-amber-600 dark:text-amber-300 ring-1 ring-amber-500/30 font-bold',
        activeBadge:
            'bg-amber-500/25 text-amber-800 dark:text-amber-300 font-bold',
    },
    {
        key: 'approved' as const,
        label: 'Đã duyệt',
        count: stats.value.approved,
        activeClass:
            'bg-blue-500/15 text-blue-600 dark:text-blue-300 ring-1 ring-blue-500/30 font-bold',
        activeBadge:
            'bg-blue-500/25 text-blue-800 dark:text-blue-300 font-bold',
    },
    {
        key: 'preparing' as const,
        label: 'Đang soạn',
        count: stats.value.preparing,
        activeClass:
            'bg-indigo-500/15 text-indigo-600 dark:text-indigo-300 ring-1 ring-indigo-500/30 font-bold',
        activeBadge:
            'bg-indigo-500/25 text-indigo-800 dark:text-indigo-300 font-bold',
    },
    {
        key: 'dispatch_pending_approval' as const,
        label: 'Chờ duyệt xuất',
        count: stats.value.dispatch_pending,
        activeClass:
            'bg-violet-500/15 text-violet-600 dark:text-violet-300 ring-1 ring-violet-500/30 font-bold',
        activeBadge:
            'bg-violet-500/25 text-violet-800 dark:text-violet-300 font-bold',
    },
    {
        key: 'dispatched' as const,
        label: 'Đang giao',
        count: stats.value.dispatched,
        activeClass:
            'bg-sky-500/15 text-sky-600 dark:text-sky-300 ring-1 ring-sky-500/30 font-bold',
        activeBadge:
            'bg-sky-500/25 text-sky-800 dark:text-sky-300 font-bold',
    },
    {
        key: 'partial_received' as const,
        label: 'Nhận 1 phần',
        count: stats.value.partial_received,
        activeClass:
            'bg-orange-500/15 text-orange-600 dark:text-orange-300 ring-1 ring-orange-500/30 font-bold',
        activeBadge:
            'bg-orange-500/25 text-orange-800 dark:text-orange-300 font-bold',
    },
    {
        key: 'disputed' as const,
        label: 'Tranh chấp',
        count: stats.value.disputed,
        activeClass:
            'bg-rose-500/15 text-rose-600 dark:text-rose-300 ring-1 ring-rose-500/30 font-bold',
        activeBadge:
            'bg-rose-500/25 text-rose-800 dark:text-rose-300 font-bold',
    },
    {
        key: 'completed' as const,
        label: 'Hoàn thành',
        count: stats.value.completed,
        activeClass:
            'bg-emerald-500/15 text-emerald-600 dark:text-emerald-300 ring-1 ring-emerald-500/30 font-bold',
        activeBadge:
            'bg-emerald-500/25 text-emerald-800 dark:text-emerald-300 font-bold',
    },
]);

const operationalSummary = computed(
    () => props.supplyAnalytics?.operations ?? {},
);
const warehouseKpi = computed(() => props.centralWarehouseAnalytics ?? {});
const receivingVouchers = computed(() => props.receivingVouchers ?? []);
const inventorySummary = computed(() => props.inventorySummary ?? {});
const locations = ref<Array<any>>([...(props.warehouseLocations ?? [])]);

const isOverdue = (request: any) =>
    Boolean(
        request?.requested_delivery_date &&
        !terminalStatuses.includes(request.status) &&
        new Date(request.requested_delivery_date).getTime() < Date.now(),
    );

const getDueLabel = (request: any) => {
    if (!request?.requested_delivery_date) {
        return 'Chưa đặt lịch giao';
    }

    if (isOverdue(request)) {
        return `Quá hạn ${formatDate(request.requested_delivery_date)}`;
    }

    return `Giao ${formatDate(request.requested_delivery_date)}`;
};
const isLocationModalOpen = ref(false);
const isSavingLocation = ref(false);
const locationForm = ref({
    branch_id: props.centralBranch?.id ?? '',
    zone: '',
    rack: '',
    shelf: '',
    bin: '',
    location_code: '',
    is_cold_storage: false,
    is_quarantine: false,
});

const openLocationModal = () => {
    isLocationModalOpen.value = true;
};

const saveLocation = async () => {
    if (!locationForm.value.zone || !locationForm.value.location_code) {
        toast.error('Vui lòng nhập khu vực và mã vị trí kho.');

        return;
    }

    isSavingLocation.value = true;

    try {
        const res = await axios.post(
            '/api/warehouse-locations',
            locationForm.value,
        );

        if (res.data.success) {
            locations.value.push(res.data.data);
            toast.success(res.data.message || 'Đã tạo vị trí kho.');
            locationForm.value = {
                branch_id: props.centralBranch?.id ?? '',
                zone: '',
                rack: '',
                shelf: '',
                bin: '',
                location_code: '',
                is_cold_storage: false,
                is_quarantine: false,
            };
        }
    } catch (e: any) {
        toast.error(e.response?.data?.message || 'Không thể tạo vị trí kho.');
    } finally {
        isSavingLocation.value = false;
    }
};

const openPickingModal = (req: any) => {
    selectedRequest.value = req;
    pickingItems.value = req.items.map((item: any) => ({
        id: item.id,
        ingredient_name: item.ingredient?.name,
        unit_symbol: item.unit_symbol || item.ingredient?.unit?.symbol || 'đv',
        requested_quantity: Number(item.requested_quantity),
        approved_quantity: Number(
            item.approved_quantity ?? item.requested_quantity,
        ),
        actual_dispatched_quantity: Number(
            item.actual_dispatched_quantity ??
                item.approved_quantity ??
                item.requested_quantity,
        ),
        batch_id: item.batch_id || null,
        warehouse_location_id: item.warehouse_location_id || null,
        non_fefo_reason: item.non_fefo_reason || '',
        notes: item.shortage_notes || '',
    }));
    isPickingModalOpen.value = true;
};

const submitPreparePicking = async () => {
    if (!selectedRequest.value) {
        return;
    }

    isProcessing.value = true;

    try {
        const payload = {
            items: pickingItems.value.map((item) => ({
                id: item.id,
                actual_dispatched_quantity: Number(
                    item.actual_dispatched_quantity,
                ),
                batch_id: item.batch_id ? Number(item.batch_id) : null,
                warehouse_location_id: item.warehouse_location_id
                    ? Number(item.warehouse_location_id)
                    : null,
                non_fefo_reason: item.non_fefo_reason || null,
                notes: item.notes || null,
            })),
        };

        const res = await axios.post(
            `/api/supply-requests/${selectedRequest.value.id}/prepare`,
            payload,
        );

        if (res.data.success) {
            toast.success(res.data.message || 'Đã hoàn thành bước soạn hàng. Chờ Trưởng kho duyệt xuất.');
            isPickingModalOpen.value = false;
            const newStatus = res.data.data?.status || 'prepared';
            if (res.data.data) {
                selectedRequest.value = {
                    ...selectedRequest.value,
                    ...res.data.data,
                    status: newStatus,
                };
            } else {
                selectedRequest.value.status = newStatus;
            }
            const found = props.supplyRequests.find((r) => r.id === selectedRequest.value.id);
            if (found) {
                Object.assign(found, selectedRequest.value);
            }
            router.reload({ preserveState: true, only: ['supplyRequests', 'supplyAnalytics', 'warehouseTasks'] });
        }
    } catch (e: any) {
        toast.error(
            e.response?.data?.message || 'Có lỗi xảy ra khi soạn hàng.',
        );
    } finally {
        isProcessing.value = false;
    }
};

const approveDispatchManager = async () => {
    if (!selectedRequest.value) {
        return;
    }

    isProcessing.value = true;

    try {
        const res = await axios.post(
            `/api/supply-requests/${selectedRequest.value.id}/approve-dispatch`,
        );

        if (res.data.success) {
            toast.success('Đã duyệt lệnh xuất kho! Mở form bàn giao xuất kho.');
            const newStatus =
                res.data.data?.status || 'dispatch_pending_approval';
            if (res.data.data) {
                selectedRequest.value = {
                    ...selectedRequest.value,
                    ...res.data.data,
                    status: newStatus,
                };
            } else {
                selectedRequest.value.status = newStatus;
            }
            const found = props.supplyRequests.find(
                (r) => r.id === selectedRequest.value.id,
            );
            if (found) {
                Object.assign(found, selectedRequest.value);
            }

            // Mở trực tiếp form Bàn giao xuất kho để rút ngắn công đoạn
            openDispatchModal(selectedRequest.value);

            router.reload({
                preserveState: true,
                only: ['supplyRequests', 'supplyAnalytics', 'warehouseTasks'],
            });
        }
    } catch (e: any) {
        toast.error(
            e.response?.data?.message || 'Có lỗi xảy ra khi duyệt xuất.',
        );
    } finally {
        isProcessing.value = false;
    }
};

const analytics = computed(() => props.supplyAnalytics);
const maxDailyItems = computed(() =>
    Math.max(
        ...(analytics.value?.daily ?? []).map((day: any) =>
            Number(day.items || 0),
        ),
        1,
    ),
);

const formatQuantity = (amount: number | string | null | undefined) =>
    new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 3 }).format(
        Number(amount || 0),
    );

const getPriorityBadge = (priority: string) => {
    switch (priority) {
        case 'urgent':
            return {
                label: 'Cần nhập gấp',
                color: 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900/50 dark:bg-rose-950/30 dark:text-rose-300',
            };
        case 'watch':
            return {
                label: 'Theo dõi',
                color: 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-300',
            };
        default:
            return {
                label: 'Ổn định',
                color: 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/50 dark:bg-emerald-950/30 dark:text-emerald-300',
            };
    }
};

const openAnalyticsRequest = (requestId: number) => {
    const request = props.supplyRequests.find((item) => item.id === requestId);

    if (request) {
        openDetailModal(request);
    }
};

const openDetailModal = (req: any) => {
    selectedRequest.value = JSON.parse(JSON.stringify(req));
    isDetailModalOpen.value = true;
};

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
    }).format(amount || 0);
};

const formatDate = (dt: string) => {
    if (!dt) {
        return '-';
    }

    return new Date(dt).toLocaleString('vi-VN', {
        dateStyle: 'short',
        timeStyle: 'short',
    });
};

const getRequestQuantitySummary = (request: any) => {
    const items = request?.items ?? [];
    const dispatched = items.reduce(
        (sum: number, item: any) =>
            sum +
            Number(
                item.actual_dispatched_quantity ??
                    item.approved_quantity ??
                    item.requested_quantity ??
                    0,
            ),
        0,
    );
    const received = items.reduce(
        (sum: number, item: any) => sum + Number(item.received_quantity ?? 0),
        0,
    );

    return {
        dispatched,
        received,
        shortage: items.reduce(
            (sum: number, item: any) =>
                sum +
                Math.max(
                    0,
                    Number(
                        item.actual_dispatched_quantity ??
                            item.approved_quantity ??
                            item.requested_quantity ??
                            0,
                    ) - Number(item.received_quantity ?? 0),
                ),
            0,
        ),
    };
};

const getStatusBadge = (status: string) => {
    switch (status) {
        case 'pending':
            return {
                label: 'Chờ duyệt',
                color: 'border-amber-300 bg-amber-100 text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-300',
            };
        case 'approved':
            return {
                label: 'Đã duyệt (Chờ xuất)',
                color: 'border-blue-300 bg-blue-100 text-blue-800 dark:border-blue-900/50 dark:bg-blue-950/30 dark:text-blue-300',
            };
        case 'preparing':
            return {
                label: 'Đang soạn hàng',
                color: 'border-indigo-300 bg-indigo-100 text-indigo-800 dark:border-indigo-900/50 dark:bg-indigo-950/30 dark:text-indigo-300',
            };
        case 'prepared':
            return {
                label: 'Đã soạn hàng',
                color: 'border-emerald-300 bg-emerald-100 text-emerald-800 dark:border-emerald-900/50 dark:bg-emerald-950/30 dark:text-emerald-300',
            };
        case 'dispatch_pending_approval':
            return {
                label: 'Chờ duyệt xuất',
                color: 'border-violet-300 bg-violet-100 text-violet-800 dark:border-violet-900/50 dark:bg-violet-950/30 dark:text-violet-300',
            };
        case 'dispatched':
            return {
                label: 'Đang giao hàng',
                color: 'border-purple-300 bg-purple-100 text-purple-800 dark:border-purple-900/50 dark:bg-purple-950/30 dark:text-purple-300',
            };
        case 'completed':
            return {
                label: 'Hoàn thành',
                color: 'border-emerald-300 bg-emerald-100 text-emerald-800 dark:border-emerald-900/50 dark:bg-emerald-950/30 dark:text-emerald-300',
            };
        case 'partial_received':
            return {
                label: 'Nhận một phần',
                color: 'border-orange-300 bg-orange-100 text-orange-800 dark:border-orange-900/50 dark:bg-orange-950/30 dark:text-orange-300',
            };
        case 'disputed':
            return {
                label: 'Đang tranh chấp',
                color: 'border-rose-300 bg-rose-100 text-rose-800 dark:border-rose-900/50 dark:bg-rose-950/30 dark:text-rose-300',
            };
        case 'cancelled':
            return {
                label: 'Đã hủy',
                color: 'border-slate-300 bg-slate-100 text-slate-800 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300',
            };
        case 'rejected':
            return {
                label: 'Đã từ chối',
                color: 'border-rose-300 bg-rose-100 text-rose-800 dark:border-rose-900/50 dark:bg-rose-950/30 dark:text-rose-300',
            };
        default:
            return {
                label: status,
                color: 'border-gray-300 bg-gray-100 text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300',
            };
    }
};

// Handlers
const approveRequest = async () => {
    if (!selectedRequest.value) {
        return;
    }

    isProcessing.value = true;

    try {
        const payload = {
            items: selectedRequest.value.items.map((item: any) => ({
                id: item.id,
                approved_quantity: Number(
                    item.approved_quantity ?? item.requested_quantity,
                ),
            })),
        };

        const res = await axios.post(
            `/api/supply-requests/${selectedRequest.value.id}/approve`,
            payload,
        );

        if (res.data.success) {
            toast.success('Đã duyệt đơn cấp phát thành công.');
            const newStatus = res.data.data?.status || 'approved';
            if (res.data.data) {
                selectedRequest.value = {
                    ...selectedRequest.value,
                    ...res.data.data,
                    status: newStatus,
                };
            } else {
                selectedRequest.value.status = newStatus;
            }
            const found = props.supplyRequests.find((r) => r.id === selectedRequest.value.id);
            if (found) {
                Object.assign(found, selectedRequest.value);
            }
            router.reload({ preserveState: true, only: ['supplyRequests', 'supplyAnalytics', 'warehouseTasks'] });
        }
    } catch (e: any) {
        toast.error(
            e.response?.data?.message || 'Có lỗi xảy ra khi duyệt đơn.',
        );
    } finally {
        isProcessing.value = false;
    }
};

const isDispatchModalOpen = ref(false);
const dispatchForm = ref({
    seal_code: '',
    transporter_id: '' as string | number,
    manifest_id: '' as string | number,
    notes: '',
});

const isTransporterAssignmentOnly = computed(() => {
    const request = selectedRequest.value;

    return Boolean(
        request &&
            ['dispatched', 'partial_received', 'disputed', 'receiving_review'].includes(
                request.status,
            ) &&
            !request.transporter_id,
    );
});

const openDispatchModal = (request: any) => {
    selectedRequest.value = request;
    const handoverTask = request?.warehouse_tasks?.find(
        (t: any) => t.task_type === 'handover',
    );

    dispatchForm.value = {
        seal_code: '',
        transporter_id: request?.transporter_id || (handoverTask
            ? handoverTask.assigned_to
            : props.warehouseStaff[0]?.id || ''),
        manifest_id: '',
        notes: '',
    };
    isDispatchModalOpen.value = true;
};

const submitDispatchModal = async () => {
    if (!selectedRequest.value) {
        return;
    }

    isProcessing.value = true;

    try {
        const assignmentOnly = isTransporterAssignmentOnly.value;
        const url = assignmentOnly
            ? `/api/supply-requests/${selectedRequest.value.id}/transporter`
            : `/api/supply-requests/${selectedRequest.value.id}/dispatch`;
        const payload = assignmentOnly
            ? {
                  transporter_id: Number(dispatchForm.value.transporter_id),
              }
            : {
                  seal_code: dispatchForm.value.seal_code || null,
                  transporter_id: dispatchForm.value.transporter_id
                      ? Number(dispatchForm.value.transporter_id)
                      : null,
                  manifest_id: dispatchForm.value.manifest_id
                      ? Number(dispatchForm.value.manifest_id)
                      : null,
                  notes: dispatchForm.value.notes || null,
              };
        const res = await axios.post(
            url,
            payload,
        );

        if (res.data.success) {
            toast.success(
                res.data.message ||
                    'Đã xuất kho Tổng và bàn giao vận chuyển thành công!',
            );
            isDispatchModalOpen.value = false;
            const newStatus = res.data.data?.status || selectedRequest.value.status;
            if (res.data.data) {
                selectedRequest.value = {
                    ...selectedRequest.value,
                    ...res.data.data,
                    status: newStatus,
                };
            } else {
                selectedRequest.value.status = newStatus;
            }
            const found = props.supplyRequests.find((r) => r.id === selectedRequest.value.id);
            if (found) {
                Object.assign(found, selectedRequest.value);
            }
            router.reload({ preserveState: true, only: ['supplyRequests', 'supplyAnalytics', 'warehouseTasks'] });
        }
    } catch (e: any) {
        toast.error(e.response?.data?.message || 'Có lỗi xảy ra khi xuất kho.');
    } finally {
        isProcessing.value = false;
    }
};

const rejectRequest = async () => {
    if (!selectedRequest.value) {
        return;
    }

    const reason = prompt('Nhập lý do từ chối đơn yêu cầu này:');

    if (!reason) {
        return;
    }

    isProcessing.value = true;

    try {
        const res = await axios.post(
            `/api/supply-requests/${selectedRequest.value.id}/reject`,
            { reason },
        );

        if (res.data.success) {
            toast.success('Đã từ chối đơn yêu cầu.');
            selectedRequest.value.status = 'rejected';
            const found = props.supplyRequests.find((r) => r.id === selectedRequest.value.id);
            if (found) {
                found.status = 'rejected';
            }
            router.reload({ preserveState: true, only: ['supplyRequests', 'supplyAnalytics'] });
        }
    } catch (e: any) {
        toast.error(e.response?.data?.message || 'Không thể từ chối đơn.');
    } finally {
        isProcessing.value = false;
    }
};

const cancelRequest = async () => {
    if (!selectedRequest.value) {
        return;
    }

    const reason = prompt('Nhập lý do hủy đơn cấp phát:');

    if (!reason) {
        return;
    }

    isProcessing.value = true;

    try {
        const res = await axios.post(
            `/api/supply-requests/${selectedRequest.value.id}/cancel`,
            { reason },
        );

        if (res.data.success) {
            toast.success(res.data.message || 'Đã hủy đơn cấp phát.');
            isDetailModalOpen.value = false;
            router.reload();
        }
    } catch (e: any) {
        toast.error(e.response?.data?.message || 'Không thể hủy đơn cấp phát.');
    } finally {
        isProcessing.value = false;
    }
};

const createBackorder = async () => {
    if (!selectedRequest.value) {
        return;
    }

    const shortageItems = selectedRequest.value.items
        .map((item: any) => {
            const dispatched = Number(
                item.actual_dispatched_quantity ??
                    item.approved_quantity ??
                    item.requested_quantity ??
                    0,
            );
            const received = Number(item.received_quantity ?? 0);

            return {
                ingredient_id: Number(item.ingredient_id),
                shortage_quantity: Math.max(0, dispatched - received),
            };
        })
        .filter((item: any) => item.shortage_quantity > 0);

    if (!shortageItems.length) {
        toast.info('Đơn này chưa có số lượng thiếu để tạo đơn giao bù.');

        return;
    }

    if (!confirm('Tạo đơn giao bù cho toàn bộ số lượng còn thiếu?')) {
        return;
    }

    isProcessing.value = true;

    try {
        const res = await axios.post(
            `/api/supply-requests/${selectedRequest.value.id}/create-backorder`,
            { shortage_items: shortageItems },
        );

        if (res.data.success) {
            toast.success(res.data.message || 'Đã tạo đơn giao bù.');
            isDetailModalOpen.value = false;
            router.reload();
        }
    } catch (e: any) {
        toast.error(e.response?.data?.message || 'Không thể tạo đơn giao bù.');
    } finally {
        isProcessing.value = false;
    }
};

const confirmReceivingVoucher = async (voucher: any) => {
    try {
        const res = await axios.post(
            `/api/warehouse/receiving-vouchers/${voucher.id}/confirm`,
            {
                notes: 'Đã được Trưởng kho Tổng xác minh trên cockpit.',
                quality_status: 'passed',
                quality_notes: 'Đã xác minh trên cockpit.',
            },
        );
        toast.success(res.data.message || 'Đã xác minh phiếu nhập.');
        router.reload();
    } catch (e: any) {
        toast.error(
            e.response?.data?.message || 'Không thể xác minh phiếu nhập.',
        );
    }
};

const exportWarehouseReport = () => {
    window.location.href = '/inventory/central-warehouse/export';
};

const taskTypeLabel = (type: string) => {
    if (type === 'delivery') {
        return 'Giao hàng tới chi nhánh';
    }

    return type === 'handover' ? 'Bàn giao / xuất xe' : 'Soạn hàng FEFO';
};

const taskStatusLabel = (status: string) => {
    switch (status) {
        case 'in_progress':
            return 'Đang làm';
        case 'completed':
            return 'Hoàn tất';
        case 'cancelled':
            return 'Đã hủy';
        default:
            return 'Chờ nhận việc';
    }
};

const taskPriorityLabel = (priority: string) => {
    switch (priority) {
        case 'urgent':
            return 'Khẩn';
        case 'high':
            return 'Cao';
        default:
            return 'Bình thường';
    }
};

const getAssignedStaffName = (request: any, taskType = 'picking') => {
    if (!request?.warehouse_tasks || request.warehouse_tasks.length === 0) {
        return null;
    }

    const task = request.warehouse_tasks.find(
        (t: any) => t.task_type === taskType,
    );

    if (!task || !task.assignee) {
        return null;
    }

    return task.assignee.name || task.assignee.employee?.full_name || null;
};

const isCurrentAssignee = (staffId: number) => {
    if (!taskForm.value.supply_request_id) {
        return false;
    }

    const req = props.supplyRequests.find(
        (r) => r.id === Number(taskForm.value.supply_request_id),
    );

    if (!req || !req.warehouse_tasks) {
        return false;
    }

    const task = req.warehouse_tasks.find(
        (t: any) => t.task_type === taskForm.value.task_type,
    );

    return task ? Number(task.assigned_to) === Number(staffId) : false;
};

const openTaskModal = (request: any, taskType?: string) => {
    const defaultDue = request.requested_delivery_date
        ? new Date(request.requested_delivery_date).toISOString().slice(0, 16)
        : '';

    taskForm.value = {
        supply_request_id: request.id,
        assigned_to: props.warehouseStaff[0]?.id || '',
        task_type:
            taskType ||
            (request.status === 'dispatch_pending_approval'
                ? 'handover'
                : 'picking'),
        priority: request.is_emergency ? 'urgent' : 'normal',
        due_at: defaultDue,
        notes: '',
    };
    isTaskModalOpen.value = true;
};

const submitTaskAssignment = async () => {
    if (!taskForm.value.supply_request_id || !taskForm.value.assigned_to) {
        toast.error('Vui lòng chọn đơn hàng và nhân viên nhận việc.');

        return;
    }

    if (isCurrentAssignee(Number(taskForm.value.assigned_to))) {
        const staffObj = props.warehouseStaff.find(
            (s) => s.id === Number(taskForm.value.assigned_to),
        );
        const name = staffObj?.name || 'nhân viên này';
        toast.warning(
            `Nhiệm vụ này hiện đã được giao cho ${name}. Vui lòng chọn nhân viên khác nếu muốn giao lại.`,
        );

        return;
    }

    isTaskProcessing.value = true;

    try {
        const res = await axios.post('/api/warehouse/tasks/assign', {
            ...taskForm.value,
            supply_request_id: Number(taskForm.value.supply_request_id),
            assigned_to: Number(taskForm.value.assigned_to),
            due_at: taskForm.value.due_at || null,
        });

        if (res.data.success) {
            toast.success(res.data.message || 'Đã giao việc Kho Tổng.');
            const staffObj = props.warehouseStaff.find(
                (s) => s.id === Number(taskForm.value.assigned_to),
            );
            const reqId = Number(taskForm.value.supply_request_id);

            // Update selectedRequest locally if open
            if (selectedRequest.value && selectedRequest.value.id === reqId) {
                if (!selectedRequest.value.warehouse_tasks) {
                    selectedRequest.value.warehouse_tasks = [];
                }
                const existingTaskIndex = selectedRequest.value.warehouse_tasks.findIndex(
                    (t: any) => t.task_type === taskForm.value.task_type,
                );
                const newTaskData = {
                    task_type: taskForm.value.task_type,
                    assigned_to: Number(taskForm.value.assigned_to),
                    assignee: staffObj
                        ? {
                              id: staffObj.id,
                              name: staffObj.name,
                              job_title: staffObj.job_title,
                          }
                        : null,
                    status: 'assigned',
                    priority: taskForm.value.priority,
                    due_at: taskForm.value.due_at,
                };
                if (existingTaskIndex >= 0) {
                    selectedRequest.value.warehouse_tasks[existingTaskIndex] = {
                        ...selectedRequest.value.warehouse_tasks[existingTaskIndex],
                        ...newTaskData,
                    };
                } else {
                    selectedRequest.value.warehouse_tasks.push(newTaskData);
                }
            }

            // Update in props.supplyRequests
            const targetReq = props.supplyRequests.find((r) => r.id === reqId);
            if (targetReq) {
                if (!targetReq.warehouse_tasks) {
                    targetReq.warehouse_tasks = [];
                }
                const existingTaskIndex = targetReq.warehouse_tasks.findIndex(
                    (t: any) => t.task_type === taskForm.value.task_type,
                );
                const newTaskData = {
                    task_type: taskForm.value.task_type,
                    assigned_to: Number(taskForm.value.assigned_to),
                    assignee: staffObj
                        ? {
                              id: staffObj.id,
                              name: staffObj.name,
                              job_title: staffObj.job_title,
                          }
                        : null,
                    status: 'assigned',
                };
                if (existingTaskIndex >= 0) {
                    targetReq.warehouse_tasks[existingTaskIndex] = {
                        ...targetReq.warehouse_tasks[existingTaskIndex],
                        ...newTaskData,
                    };
                } else {
                    targetReq.warehouse_tasks.push(newTaskData);
                }
            }

            isTaskModalOpen.value = false;
            router.reload({ preserveState: true, only: ['supplyRequests', 'warehouseTasks'] });
        }
    } catch (e: any) {
        toast.error(
            e.response?.data?.message ||
                'Không thể giao việc cho nhân viên Kho Tổng.',
        );
    } finally {
        isTaskProcessing.value = false;
    }
};

const updateTaskStatus = async (task: any, status: string) => {
    try {
        const res = await axios.post(`/api/warehouse/tasks/${task.id}/status`, {
            status,
        });

        if (res.data.success) {
            task.status = status;
            toast.success(res.data.message || 'Đã cập nhật tiến độ nhiệm vụ.');
        }
    } catch (e: any) {
        toast.error(
            e.response?.data?.message || 'Không thể cập nhật nhiệm vụ.',
        );
    }
};

const getSupplyRequestById = (id: number) =>
    props.supplyRequests.find((request) => request.id === id);

const saveIngredientPrices = async () => {
    isSavingPrices.value = true;

    try {
        const res = await axios.post('/api/warehouse/ingredient-prices', {
            prices: priceRows.value.map((row) => ({
                ingredient_id: row.ingredient_id,
                average_cost: Number(row.average_cost || 0),
            })),
        });

        if (res.data.success) {
            toast.success(res.data.message || 'Đã cập nhật đơn giá Kho Tổng.');
            router.reload();
        }
    } catch (e: any) {
        toast.error(
            e.response?.data?.message ||
                'Không thể cập nhật đơn giá nguyên liệu.',
        );
    } finally {
        isSavingPrices.value = false;
    }
};

// ── SMART ALLOCATION (FAIR-SHARE) ──────────────────────────────────────────
const isSmartModalOpen = ref(false);
const smartSuggestions = ref<Array<any>>([]);
const isLoadingSmart = ref(false);

const runSmartAllocation = async () => {
    const pendingIds = props.supplyRequests
        .filter((r) => r.status === 'pending' || r.status === 'approved')
        .map((r) => r.id);

    if (pendingIds.length === 0) {
        toast.info('Không có đơn hàng nào ở trạng thái chờ duyệt để phân bổ.');

        return;
    }

    isLoadingSmart.value = true;

    try {
        const res = await axios.post('/api/supply-requests/smart-allocation', {
            supply_request_ids: pendingIds,
        });

        if (res.data.success) {
            smartSuggestions.value = res.data.suggestions || [];
            isSmartModalOpen.value = true;
        }
    } catch (e: any) {
        toast.error(
            e.response?.data?.message ||
                'Có lỗi khi tính toán phân bổ thông minh.',
        );
    } finally {
        isLoadingSmart.value = false;
    }
};

// ── CENTRAL KITCHEN (SƠ CHẾ & SẢN XUẤT) ────────────────────────────────────
const isKitchenModalOpen = ref(false);
const workOrders = ref<Array<any>>([]);
const boms = ref<Array<any>>([]);
const newWo = ref({
    output_ingredient_id: props.ingredients[0]?.id || 1,
    target_quantity: 10,
    central_bom_id: null as number | null,
    notes: '',
});

const openKitchenModal = async () => {
    isKitchenModalOpen.value = true;

    try {
        const [resWo, resBom] = await Promise.all([
            axios.get('/api/central-kitchen/work-orders'),
            axios.get('/api/central-kitchen/boms'),
        ]);
        workOrders.value = resWo.data.work_orders || [];
        boms.value = resBom.data.boms || [];
    } catch (e: any) {
        toast.error('Không thể tải dữ liệu Central Kitchen.');
    }
};

const createWorkOrder = async () => {
    try {
        const res = await axios.post(
            '/api/central-kitchen/work-orders',
            newWo.value,
        );
        toast.success(
            res.data.message || 'Tạo đơn sơ chế sản xuất thành công.',
        );
        openKitchenModal();
    } catch (e: any) {
        toast.error(e.response?.data?.message || 'Lỗi khi tạo đơn sơ chế.');
    }
};

const executeWo = async (wo: any) => {
    const yieldQtyStr = prompt(
        `Nhập số lượng thực tế sơ chế thu hồi (${wo.output_ingredient?.name}):`,
        wo.target_quantity,
    );

    if (!yieldQtyStr) {
        return;
    }

    const yieldQty = parseFloat(yieldQtyStr);

    if (isNaN(yieldQty) || yieldQty <= 0) {
        return;
    }

    try {
        const res = await axios.post(
            `/api/central-kitchen/work-orders/${wo.id}/execute`,
            {
                actual_yield_quantity: yieldQty,
            },
        );
        toast.success(
            res.data.message || 'Đã hoàn tất sơ chế & nhập kho lô mới.',
        );
        openKitchenModal();
    } catch (e: any) {
        toast.error(
            e.response?.data?.message || 'Lỗi khi hoàn tất đơn sơ chế.',
        );
    }
};

// ── DELIVERY MANIFESTS (CHUYẾN XE GIAO HÀNG) ──────────────────────────────
const isManifestModalOpen = ref(false);
const manifests = ref<Array<any>>([]);

const openManifestModal = async () => {
    isManifestModalOpen.value = true;

    try {
        const res = await axios.get('/api/delivery-manifests');
        manifests.value = res.data.manifests || [];
    } catch (e: any) {
        toast.error('Không thể tải danh sách chuyến xe.');
    }
};

const createManifest = async () => {
    const selectedApproved = props.supplyRequests
        .filter((r) => r.status === 'approved' || r.status === 'preparing')
        .map((r) => r.id);

    if (selectedApproved.length === 0) {
        toast.info('Không có đơn hàng đã duyệt nào để gom chuyến xe.');

        return;
    }

    try {
        const res = await axios.post('/api/delivery-manifests', {
            route_name: 'Tuyến Nội Thành TP.HCM',
            driver_name: 'Tài xế Nguyễn Văn A',
            vehicle_number: '51C-999.88',
            supply_request_ids: selectedApproved,
        });
        toast.success(res.data.message || 'Tạo chuyến xe thành công.');
        openManifestModal();
    } catch (e: any) {
        toast.error(e.response?.data?.message || 'Lỗi tạo chuyến xe.');
    }
};

const dispatchManifest = async (m: any) => {
    try {
        const res = await axios.post(
            `/api/delivery-manifests/${m.id}/dispatch`,
            {
                seal_code: 'SEAL-' + Math.floor(Math.random() * 8999 + 1000),
            },
        );
        toast.success(res.data.message || 'Đã xuất bến chuyến xe.');
        openManifestModal();
        router.reload();
    } catch (e: any) {
        toast.error(e.response?.data?.message || 'Lỗi xuất bến chuyến xe.');
    }
};

// ── BATCH RECALL (THU HỒI LÔ KHẨN CẤP) ──────────────────────────────────
const isRecallModalOpen = ref(false);
const recallOrders = ref<Array<any>>([]);
const recallBatchId = ref<number | string>('');
const recallReason = ref('');

const openRecallModal = async () => {
    isRecallModalOpen.value = true;

    try {
        const res = await axios.get('/api/batch-recalls');
        recallOrders.value = res.data.recall_orders || [];
    } catch (e: any) {
        toast.error('Không thể tải lịch sử thu hồi lô.');
    }
};

const submitRecall = async () => {
    if (!recallBatchId.value || !recallReason.value) {
        toast.error('Vui lòng nhập ID lô và lý do thu hồi.');

        return;
    }

    try {
        const res = await axios.post('/api/batch-recalls/initiate', {
            batch_id: Number(recallBatchId.value),
            severity: 'critical',
            reason: recallReason.value,
        });
        toast.success(
            res.data.message ||
                'Đã phát lệnh thu hồi lô khẩn cấp toàn hệ thống!',
        );
        openRecallModal();
    } catch (e: any) {
        toast.error(e.response?.data?.message || 'Không thể thu hồi lô.');
    }
};
</script>

<template>
    <Head title="Trung tâm Điều phối Kho Tổng" />

    <div class="mx-auto w-full max-w-[1600px] space-y-6 p-4 sm:p-6 lg:p-8">
        <div class="flex items-center justify-between gap-3 text-xs">
            <Link
                href="/inventory/central-warehouse"
                class="inline-flex items-center gap-1 font-semibold text-indigo-300 hover:text-indigo-200"
                >← Tổng quan Kho Tổng</Link
            >
            <span
                class="rounded-full border border-indigo-500/20 bg-indigo-950/10 px-3 py-1 text-indigo-300"
                >Workspace: Đơn cấp phát</span
            >
        </div>
        <!-- Header Section -->
        <div
            class="flex flex-col gap-4 rounded-2xl border border-indigo-100/90 bg-gradient-to-r from-indigo-50/90 via-slate-50 to-purple-50/60 p-4 text-slate-900 shadow-xs backdrop-blur-md md:flex-row md:items-center md:justify-between dark:border-slate-800 dark:bg-black/80 dark:from-[#080b12] dark:via-black dark:to-[#080b12] dark:text-white"
        >
            <div class="space-y-1">
                <div class="flex items-center gap-3">
                    <div
                        class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-indigo-600 text-white shadow-sm shadow-indigo-600/20 backdrop-blur-md dark:border dark:border-indigo-500/30 dark:bg-indigo-600/25 dark:text-indigo-400"
                    >
                        <Warehouse class="size-5" />
                    </div>
                    <div>
                        <h1
                            class="text-lg font-black tracking-tight text-slate-900 md:text-xl lg:text-2xl dark:text-white"
                        >
                            Trung tâm Điều phối Kho Tổng
                        </h1>
                        <p class="text-xs text-slate-600 dark:text-slate-400">
                            Quản lý cấp phát nguyên liệu đồng bộ cho toàn bộ
                            chuỗi nhà hàng
                        </p>
                    </div>
                </div>
            </div>

            <!-- Kho Tổng là kho độc lập, không phải một chi nhánh -->
            <div
                class="flex items-center gap-3 rounded-xl border border-slate-200/80 bg-white/90 px-3.5 py-2 text-xs shadow-2xs backdrop-blur-sm dark:border-white/10 dark:bg-black/50"
            >
                <Building2
                    class="size-4 text-indigo-500 dark:text-indigo-400"
                />
                <div class="text-xs">
                    <div
                        class="text-[9px] font-bold tracking-wider text-slate-500 uppercase dark:text-slate-400"
                    >
                        Kho xuất hàng
                    </div>
                    <div class="font-extrabold text-slate-900 dark:text-white">
                        Kho Tổng độc lập
                    </div>
                    <div class="text-[10px] text-slate-500 dark:text-slate-400">
                        Điều phối riêng, không thuộc chi nhánh
                    </div>
                </div>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-end gap-2">
            <Button
                v-if="false && canManageWarehouse"
                variant="outline"
                size="sm"
                class="gap-1.5 text-xs"
                @click="exportWarehouseReport"
            >
                <FileDown class="h-4 w-4" /> Xuất báo cáo CSV
            </Button>
            <Button
                v-if="false && locations.length"
                variant="outline"
                size="sm"
                class="gap-1.5 text-xs"
                @click="openLocationModal"
            >
                <MapPin class="h-4 w-4" /> {{ locations.length }} vị trí kho
            </Button>
            <Button
                v-if="false && canManageWarehouse"
                variant="outline"
                size="sm"
                class="gap-1.5 text-xs"
                @click="openLocationModal"
            >
                <MapPin class="h-4 w-4" /> Thêm vị trí
            </Button>
        </div>

        <!-- Metrics are represented in the action queue below; keep a single visual hierarchy. -->
        <div class="hidden">
            <Card class="border-border/80 shadow-sm transition hover:shadow">
                <CardContent class="flex items-center justify-between p-4">
                    <div>
                        <p class="text-xs font-medium text-muted-foreground">
                            Tổng Đơn
                        </p>
                        <p class="mt-1 text-2xl font-bold text-foreground">
                            {{ stats.total }}
                        </p>
                    </div>
                    <div
                        class="rounded-lg bg-muted p-2.5 text-muted-foreground"
                    >
                        <Boxes class="h-5 w-5" />
                    </div>
                </CardContent>
            </Card>

            <Card
                class="border-amber-500/20 bg-amber-950/10 shadow-sm transition hover:shadow"
            >
                <CardContent class="flex items-center justify-between p-4">
                    <div>
                        <p class="text-xs font-medium text-amber-300">
                            Chờ Kho Duyệt
                        </p>
                        <p class="mt-1 text-2xl font-bold text-amber-100">
                            {{ stats.pending }}
                        </p>
                    </div>
                    <div
                        class="rounded-lg bg-amber-950/50 p-2.5 text-amber-300"
                    >
                        <Clock class="h-5 w-5" />
                    </div>
                </CardContent>
            </Card>

            <Card
                class="border-blue-500/20 bg-blue-950/10 shadow-sm transition hover:shadow"
            >
                <CardContent class="flex items-center justify-between p-4">
                    <div>
                        <p class="text-xs font-medium text-blue-300">
                            Đã Duyệt (Chờ Xuất)
                        </p>
                        <p class="mt-1 text-2xl font-bold text-blue-100">
                            {{ stats.approved }}
                        </p>
                    </div>
                    <div class="rounded-lg bg-blue-950/50 p-2.5 text-blue-300">
                        <PackageCheck class="h-5 w-5" />
                    </div>
                </CardContent>
            </Card>

            <Card
                class="border-purple-500/20 bg-purple-950/10 shadow-sm transition hover:shadow"
            >
                <CardContent class="flex items-center justify-between p-4">
                    <div>
                        <p class="text-xs font-medium text-purple-300">
                            Đang Giao Hàng
                        </p>
                        <p class="mt-1 text-2xl font-bold text-purple-100">
                            {{ stats.dispatched }}
                        </p>
                    </div>
                    <div
                        class="rounded-lg bg-purple-950/50 p-2.5 text-purple-300"
                    >
                        <Truck class="h-5 w-5" />
                    </div>
                </CardContent>
            </Card>

            <Card
                class="border-emerald-500/20 bg-emerald-950/10 shadow-sm transition hover:shadow"
            >
                <CardContent class="flex items-center justify-between p-4">
                    <div>
                        <p class="text-xs font-medium text-emerald-300">
                            Hoàn Thành
                        </p>
                        <p class="mt-1 text-2xl font-bold text-emerald-100">
                            {{ stats.completed }}
                        </p>
                    </div>
                    <div
                        class="rounded-lg bg-emerald-950/50 p-2.5 text-emerald-300"
                    >
                        <CheckCircle2 class="h-5 w-5" />
                    </div>
                </CardContent>
            </Card>
        </div>

        <div
            class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border bg-card/70 px-4 py-3 shadow-sm"
        >
            <div>
                <p
                    class="text-xs font-bold tracking-[0.16em] text-indigo-400 uppercase"
                >
                    Điều hành hôm nay
                </p>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ openRequests.length }} đơn đang mở ·
                    {{ overdueRequests.length }} đơn quá hạn SLA
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Link
                    href="/inventory/central-warehouse/stock"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-background px-3 py-2 text-xs font-semibold text-foreground transition hover:border-indigo-500/50 hover:text-indigo-300"
                    ><Boxes class="h-3.5 w-3.5" /> Tồn kho</Link
                >
                <Link
                    href="/inventory/central-warehouse/receiving"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-background px-3 py-2 text-xs font-semibold text-foreground transition hover:border-indigo-500/50 hover:text-indigo-300"
                    ><PackageCheck class="h-3.5 w-3.5" /> Phiếu nhập</Link
                >
                <Button
                    v-if="canManageWarehouse"
                    variant="outline"
                    size="sm"
                    class="gap-1.5 text-xs"
                    @click="runSmartAllocation"
                    ><Lightbulb class="h-3.5 w-3.5" /> Phân bổ thông
                    minh</Button
                >
                <Button
                    v-if="canManageWarehouse"
                    variant="outline"
                    size="sm"
                    class="gap-1.5 text-xs"
                    @click="exportWarehouseReport"
                    ><FileDown class="h-3.5 w-3.5" /> Xuất báo cáo</Button
                >
                <Button
                    v-if="canManageWarehouse"
                    variant="outline"
                    size="sm"
                    class="gap-1.5 text-xs"
                    @click="openLocationModal"
                    ><MapPin class="h-3.5 w-3.5" /> Vị trí ({{
                        locations.length
                    }})</Button
                >
            </div>
        </div>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <button
                v-for="item in actionQueue"
                :key="item.key"
                type="button"
                class="group rounded-2xl border border-border bg-card p-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:border-indigo-500/40 hover:shadow-lg"
                @click="activeTab = item.key"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p
                            class="text-xs font-bold tracking-wide text-muted-foreground uppercase"
                        >
                            {{ item.label }}
                        </p>
                        <p class="mt-2 text-3xl font-bold text-foreground">
                            {{ item.count }}
                        </p>
                    </div>
                    <div
                        class="rounded-xl bg-indigo-500/10 p-2.5 text-indigo-300 transition group-hover:bg-indigo-500/20"
                    >
                        <component :is="item.icon" class="h-5 w-5" />
                    </div>
                </div>
                <p class="mt-3 text-[11px] leading-4 text-muted-foreground">
                    {{ item.description }}
                </p>
                <span
                    class="mt-3 inline-flex items-center gap-1 text-[11px] font-semibold text-indigo-300"
                    >Mở danh sách <ArrowUpRight class="h-3 w-3"
                /></span>
            </button>
        </section>

        <WarehouseAiRecommendations
            :initial-ai="props.centralWarehouseAi"
            context="requests"
            :max="3"
        />

        <section class="grid gap-4 xl:grid-cols-[1.35fr_0.65fr]">
            <Card class="overflow-hidden border-border shadow-sm">
                <CardHeader class="border-b border-border bg-muted/20 py-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <CardTitle
                                class="flex items-center gap-2 text-base font-bold text-foreground"
                                ><AlertCircle class="h-4 w-4 text-amber-400" />
                                Hàng đợi cần xử lý</CardTitle
                            >
                            <CardDescription class="mt-1 text-xs"
                                >Ưu tiên đơn quá hạn và đơn đang chặn luồng xuất
                                hàng.</CardDescription
                            >
                        </div>
                        <span
                            class="rounded-full border border-amber-500/20 bg-amber-500/10 px-2.5 py-1 text-[11px] font-semibold text-amber-300"
                            >{{ overdueRequests.length }} quá hạn</span
                        >
                    </div>
                </CardHeader>
                <CardContent class="p-0">
                    <div
                        v-if="attentionRequests.length"
                        class="divide-y divide-border"
                    >
                        <button
                            v-for="request in attentionRequests"
                            :key="request.id"
                            type="button"
                            class="flex w-full items-center gap-3 p-4 text-left transition hover:bg-muted/30"
                            @click="openDetailModal(request)"
                        >
                            <div
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl"
                                :class="
                                    isOverdue(request)
                                        ? 'bg-rose-500/10 text-rose-400'
                                        : 'bg-indigo-500/10 text-indigo-300'
                                "
                            >
                                <AlertTriangle
                                    v-if="isOverdue(request)"
                                    class="h-4 w-4"
                                /><ClipboardList v-else class="h-4 w-4" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span
                                        class="font-mono text-xs font-bold text-indigo-300"
                                        >{{ request.request_code }}</span
                                    >
                                    <span
                                        :class="[
                                            'rounded-full border px-2 py-0.5 text-[10px] font-semibold',
                                            getStatusBadge(request.status)
                                                .color,
                                        ]"
                                        >{{
                                            getStatusBadge(request.status).label
                                        }}</span
                                    >
                                </div>
                                <p
                                    class="mt-1 truncate text-xs font-semibold text-foreground"
                                >
                                    {{
                                        request.to_branch?.name ||
                                        'Chưa xác định chi nhánh'
                                    }}
                                    · {{ request.items?.length || 0 }} mặt hàng
                                </p>
                                <p
                                    class="mt-1 text-[11px]"
                                    :class="
                                        isOverdue(request)
                                            ? 'text-rose-400'
                                            : 'text-muted-foreground'
                                    "
                                >
                                    {{ getDueLabel(request) }} ·
                                    {{ formatCurrency(request.total_amount) }}
                                </p>
                            </div>
                            <ArrowUpRight
                                class="h-4 w-4 shrink-0 text-muted-foreground"
                            />
                        </button>
                    </div>
                    <div
                        v-else
                        class="flex flex-col items-center justify-center px-6 py-10 text-center"
                    >
                        <CheckCircle2 class="h-8 w-8 text-emerald-400" />
                        <p class="mt-3 text-sm font-semibold text-foreground">
                            Không có việc tồn đọng
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Các đơn cấp phát đang nằm trong SLA xử lý.
                        </p>
                    </div>
                </CardContent>
            </Card>

            <Card class="border-border shadow-sm">
                <CardHeader class="border-b border-border bg-muted/20 py-4">
                    <CardTitle
                        class="flex items-center gap-2 text-base font-bold text-foreground"
                        ><Activity class="h-4 w-4 text-sky-400" /> Sức khỏe Kho
                        Tổng</CardTitle
                    >
                    <CardDescription class="mt-1 text-xs"
                        >Các chỉ số giúp quyết định có thể duyệt và xuất
                        hàng.</CardDescription
                    >
                </CardHeader>
                <CardContent class="space-y-4 p-4">
                    <div>
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-muted-foreground"
                                >Fill rate cấp phát</span
                            ><strong class="text-emerald-300"
                                >{{
                                    operationalSummary.fill_rate_percent ?? 100
                                }}%</strong
                            >
                        </div>
                        <div
                            class="mt-2 h-2 overflow-hidden rounded-full bg-muted"
                        >
                            <div
                                class="h-full rounded-full bg-emerald-500 transition-all"
                                :style="{
                                    width: `${Math.min(Number(operationalSummary.fill_rate_percent ?? 100), 100)}%`,
                                }"
                            ></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-muted-foreground"
                                >OTIF giao đúng đủ</span
                            ><strong class="text-indigo-300"
                                >{{ warehouseKpi.otif_percent ?? 100 }}%</strong
                            >
                        </div>
                        <div
                            class="mt-2 h-2 overflow-hidden rounded-full bg-muted"
                        >
                            <div
                                class="h-full rounded-full bg-indigo-500 transition-all"
                                :style="{
                                    width: `${Math.min(Number(warehouseKpi.otif_percent ?? 100), 100)}%`,
                                }"
                            ></div>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2 pt-1">
                        <div
                            class="rounded-xl border border-border bg-muted/20 p-3"
                        >
                            <p class="text-[11px] text-muted-foreground">
                                Tồn khả dụng
                            </p>
                            <p class="mt-1 text-base font-bold text-foreground">
                                {{
                                    formatQuantity(
                                        inventorySummary.available_quantity,
                                    )
                                }}
                            </p>
                        </div>
                        <div
                            class="rounded-xl border border-border bg-muted/20 p-3"
                        >
                            <p class="text-[11px] text-muted-foreground">
                                Sắp hết hàng
                            </p>
                            <p class="mt-1 text-base font-bold text-amber-300">
                                {{ inventorySummary.low_stock_count || 0 }}
                            </p>
                        </div>
                        <div
                            class="rounded-xl border border-border bg-muted/20 p-3"
                        >
                            <p class="text-[11px] text-muted-foreground">
                                Lô sắp hết hạn
                            </p>
                            <p class="mt-1 text-base font-bold text-orange-300">
                                {{ inventorySummary.expiring_soon_count || 0 }}
                            </p>
                        </div>
                        <div
                            class="rounded-xl border border-border bg-muted/20 p-3"
                        >
                            <p class="text-[11px] text-muted-foreground">
                                Chênh lệch nhận
                            </p>
                            <p class="mt-1 text-base font-bold text-rose-300">
                                {{
                                    receivingSummary?.discrepancy_vouchers || 0
                                }}
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </section>

        <Card class="border-border shadow-sm">
            <CardHeader class="border-b border-border bg-muted/20 py-4">
                <div
                    class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between"
                >
                    <div>
                        <CardTitle
                            class="flex items-center gap-2 text-base font-bold text-foreground"
                            ><TrendingUp class="h-4 w-4 text-indigo-400" />
                            Luồng xử lý đơn cấp phát</CardTitle
                        >
                        <CardDescription class="mt-1 text-xs"
                            >Chọn một bước để mở đúng danh sách cần thao
                            tác.</CardDescription
                        >
                    </div>
                    <div class="text-xs text-muted-foreground">
                        {{ stats.total }} đơn trong kỳ dữ liệu
                    </div>
                </div>
            </CardHeader>
            <CardContent class="overflow-x-auto p-4">
                <div class="grid min-w-[760px] grid-cols-6 gap-2">
                    <button
                        v-for="(stage, index) in pipelineStages"
                        :key="stage.key"
                        type="button"
                        class="relative rounded-xl border p-3 text-left transition hover:border-indigo-500/50 hover:bg-indigo-500/5"
                        :class="
                            activeTab === stage.key
                                ? 'border-indigo-500/60 bg-indigo-500/10'
                                : 'border-border bg-background'
                        "
                        @click="activeTab = stage.key"
                    >
                        <span
                            class="text-[10px] font-bold tracking-wide text-muted-foreground uppercase"
                            >0{{ index + 1 }}</span
                        >
                        <p class="mt-2 text-xs font-semibold text-foreground">
                            {{ stage.label }}
                        </p>
                        <p class="mt-1 text-xl font-bold text-indigo-300">
                            {{ stage.count }}
                        </p>
                        <ArrowUpRight
                            class="absolute right-3 bottom-3 h-3.5 w-3.5 text-muted-foreground"
                        />
                    </button>
                </div>
            </CardContent>
        </Card>

        <!-- Operational cockpit -->
        <section v-if="false" class="space-y-4">
            <div
                class="flex flex-col justify-between gap-2 sm:flex-row sm:items-end"
            >
                <div>
                    <p
                        class="text-xs font-bold tracking-[0.16em] text-indigo-600 uppercase"
                    >
                        Cockpit vận hành
                    </p>
                    <h2
                        class="mt-1 text-xl font-bold tracking-tight text-foreground"
                    >
                        Việc Trưởng kho cần xử lý ngay
                    </h2>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Đơn quá hạn, nhận hàng, tồn kho và tranh chấp được gom
                        vào một màn hình.
                    </p>
                </div>
                <div class="text-[11px] text-muted-foreground">
                    Cập nhật theo dữ liệu hiện tại
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-8">
                <button
                    type="button"
                    class="rounded-xl border border-amber-500/20 bg-amber-950/10 p-3 text-left"
                    @click="activeTab = 'pending'"
                >
                    <p
                        class="text-[10px] font-semibold text-amber-300 uppercase"
                    >
                        Chờ duyệt
                    </p>
                    <p class="mt-1 text-xl font-bold text-amber-100">
                        {{ stats.pending }}
                    </p>
                </button>
                <button
                    type="button"
                    class="rounded-xl border border-rose-500/20 bg-rose-950/10 p-3 text-left"
                    @click="
                        searchQuery = '';
                        activeTab = 'all';
                    "
                >
                    <p
                        class="text-[10px] font-semibold text-rose-300 uppercase"
                    >
                        Quá hạn
                    </p>
                    <p class="mt-1 text-xl font-bold text-rose-100">
                        {{ operationalSummary.overdue_requests || 0 }}
                    </p>
                </button>
                <button
                    type="button"
                    class="rounded-xl border border-indigo-500/20 bg-indigo-950/10 p-3 text-left"
                    @click="activeTab = 'preparing'"
                >
                    <p
                        class="text-[10px] font-semibold text-indigo-300 uppercase"
                    >
                        Đang soạn
                    </p>
                    <p class="mt-1 text-xl font-bold text-indigo-100">
                        {{ stats.preparing }}
                    </p>
                </button>
                <button
                    type="button"
                    class="rounded-xl border border-violet-500/20 bg-violet-950/10 p-3 text-left"
                    @click="activeTab = 'dispatch_pending_approval'"
                >
                    <p
                        class="text-[10px] font-semibold text-violet-300 uppercase"
                    >
                        Chờ duyệt xuất
                    </p>
                    <p class="mt-1 text-xl font-bold text-violet-100">
                        {{ stats.dispatch_pending }}
                    </p>
                </button>
                <button
                    type="button"
                    class="rounded-xl border border-orange-500/20 bg-orange-950/10 p-3 text-left"
                    @click="activeTab = 'partial_received'"
                >
                    <p
                        class="text-[10px] font-semibold text-orange-300 uppercase"
                    >
                        Nhận một phần
                    </p>
                    <p class="mt-1 text-xl font-bold text-orange-100">
                        {{ stats.partial_received }}
                    </p>
                </button>
                <button
                    type="button"
                    class="rounded-xl border border-rose-500/20 bg-rose-950/10 p-3 text-left"
                    @click="activeTab = 'disputed'"
                >
                    <p
                        class="text-[10px] font-semibold text-rose-300 uppercase"
                    >
                        Tranh chấp
                    </p>
                    <p class="mt-1 text-xl font-bold text-rose-100">
                        {{ stats.disputed }}
                    </p>
                </button>
                <div
                    class="rounded-xl border border-emerald-500/20 bg-emerald-950/10 p-3"
                >
                    <p
                        class="text-[10px] font-semibold text-emerald-300 uppercase"
                    >
                        Fill rate
                    </p>
                    <p class="mt-1 text-xl font-bold text-emerald-100">
                        {{ operationalSummary.fill_rate_percent ?? 100 }}%
                    </p>
                </div>
                <div
                    class="rounded-xl border border-sky-500/20 bg-sky-950/10 p-3"
                >
                    <p class="text-[10px] font-semibold text-sky-300 uppercase">
                        Tồn thấp
                    </p>
                    <p class="mt-1 text-xl font-bold text-sky-100">
                        {{ inventorySummary.low_stock_count || 0 }}
                    </p>
                </div>
            </div>

            <Card
                v-if="operationalSummary.branch_operations?.length"
                class="border-border shadow-sm"
            >
                <CardHeader class="border-b border-border bg-muted/20 py-3">
                    <CardTitle class="text-sm font-bold text-foreground"
                        >SLA theo chi nhánh</CardTitle
                    >
                    <CardDescription class="text-xs"
                        >Chọn một dòng để lọc ngay các yêu cầu của chi nhánh cần
                        ưu tiên.</CardDescription
                    >
                </CardHeader>
                <CardContent class="p-0">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead
                                class="border-b border-border bg-muted/50 text-muted-foreground"
                            >
                                <tr>
                                    <th class="p-3">Chi nhánh</th>
                                    <th class="p-3 text-right">Đơn mở</th>
                                    <th class="p-3 text-right">Quá hạn</th>
                                    <th class="p-3 text-right">Tranh chấp</th>
                                    <th class="p-3 text-right">Fill rate</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr
                                    v-for="branch in operationalSummary.branch_operations"
                                    :key="branch.id"
                                    class="cursor-pointer hover:bg-muted/30"
                                    @click="
                                        selectedBranchFilter = branch.id;
                                        activeTab = 'all';
                                    "
                                >
                                    <td
                                        class="p-3 font-semibold text-foreground"
                                    >
                                        {{ branch.name }}
                                    </td>
                                    <td class="p-3 text-right text-indigo-300">
                                        {{ branch.open_requests }}
                                    </td>
                                    <td class="p-3 text-right text-rose-400">
                                        {{ branch.overdue_requests }}
                                    </td>
                                    <td class="p-3 text-right text-orange-300">
                                        {{ branch.disputed_requests }}
                                    </td>
                                    <td
                                        class="p-3 text-right font-bold"
                                        :class="
                                            branch.fill_rate_percent < 90
                                                ? 'text-rose-400'
                                                : 'text-emerald-400'
                                        "
                                    >
                                        {{ branch.fill_rate_percent }}%
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            <div class="grid gap-4 xl:grid-cols-[1.25fr_1fr]">
                <Card class="border-border shadow-sm">
                    <CardHeader class="border-b border-border bg-muted/20 py-3">
                        <CardTitle class="text-sm font-bold text-foreground"
                            >Phiếu nhập cần xác minh</CardTitle
                        >
                        <CardDescription class="text-xs"
                            >GRN có chênh lệch phải được Trưởng kho xem xét
                            trước khi đóng.</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="p-0">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead
                                    class="border-b border-border bg-muted/50 text-muted-foreground"
                                >
                                    <tr>
                                        <th class="p-3">Mã phiếu</th>
                                        <th class="p-3">Người nhận</th>
                                        <th class="p-3">Ngày nhận</th>
                                        <th class="p-3 text-right">
                                            Chênh lệch
                                        </th>
                                        <th class="p-3 text-right">Xử lý</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border">
                                    <tr
                                        v-if="
                                            receivingVouchers.filter(
                                                (voucher) =>
                                                    [
                                                        'discrepancy',
                                                        'pending_review',
                                                    ].includes(voucher.status),
                                            ).length === 0
                                        "
                                    >
                                        <td
                                            colspan="5"
                                            class="p-5 text-center text-muted-foreground"
                                        >
                                            Không có phiếu nhập đang chờ xử lý.
                                        </td>
                                    </tr>
                                    <tr
                                        v-for="voucher in receivingVouchers
                                            .filter((item) =>
                                                [
                                                    'discrepancy',
                                                    'pending_review',
                                                ].includes(item.status),
                                            )
                                            .slice(0, 8)"
                                        :key="voucher.id"
                                    >
                                        <td
                                            class="p-3 font-mono font-bold text-indigo-400"
                                        >
                                            {{ voucher.voucher_code }}
                                        </td>
                                        <td
                                            class="p-3 font-medium text-foreground"
                                        >
                                            {{
                                                voucher.received_by?.name || '-'
                                            }}
                                        </td>
                                        <td class="p-3 text-muted-foreground">
                                            {{
                                                formatDate(voucher.received_at)
                                            }}
                                        </td>
                                        <td
                                            class="p-3 text-right font-bold text-rose-400"
                                        >
                                            {{
                                                formatQuantity(
                                                    Math.abs(
                                                        Number(
                                                            voucher.total_discrepancy_qty ||
                                                                0,
                                                        ),
                                                    ),
                                                )
                                            }}
                                        </td>
                                        <td class="p-3 text-right">
                                            <Button
                                                size="sm"
                                                class="h-7 bg-indigo-600 text-[10px] text-white hover:bg-indigo-700"
                                                @click="
                                                    confirmReceivingVoucher(
                                                        voucher,
                                                    )
                                                "
                                                >Xác minh</Button
                                            >
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>

                <Card class="border-border shadow-sm">
                    <CardHeader class="border-b border-border bg-muted/20 py-3">
                        <CardTitle class="text-sm font-bold text-foreground"
                            >Sức khỏe tồn Kho Tổng</CardTitle
                        >
                        <CardDescription class="text-xs"
                            >Giá trị tồn và các tín hiệu cần kiểm tra trong
                            ngày.</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="grid grid-cols-2 gap-3 p-4 text-xs">
                        <div
                            class="rounded-lg border border-border bg-muted/20 p-3"
                        >
                            <span class="text-muted-foreground">Mặt hàng</span
                            ><strong
                                class="mt-1 block text-lg text-foreground"
                                >{{
                                    inventorySummary.ingredient_count || 0
                                }}</strong
                            >
                        </div>
                        <div
                            class="rounded-lg border border-border bg-muted/20 p-3"
                        >
                            <span class="text-muted-foreground"
                                >Số lượng tồn</span
                            ><strong
                                class="mt-1 block text-lg text-foreground"
                                >{{
                                    formatQuantity(
                                        inventorySummary.on_hand_quantity || 0,
                                    )
                                }}</strong
                            >
                        </div>
                        <div
                            class="rounded-lg border border-border bg-muted/20 p-3"
                        >
                            <span class="text-muted-foreground"
                                >Giá trị tồn</span
                            ><strong
                                class="mt-1 block text-sm text-emerald-400"
                                >{{
                                    formatCurrency(
                                        inventorySummary.on_hand_value || 0,
                                    )
                                }}</strong
                            >
                        </div>
                        <div
                            class="rounded-lg border border-border bg-muted/20 p-3"
                        >
                            <span class="text-muted-foreground"
                                >Lô sắp hết hạn</span
                            ><strong
                                class="mt-1 block text-lg text-amber-300"
                                >{{
                                    inventorySummary.expiring_soon_count || 0
                                }}</strong
                            >
                        </div>
                        <div
                            class="rounded-lg border border-border bg-muted/20 p-3"
                        >
                            <span class="text-muted-foreground"
                                >Vị trí cách ly</span
                            ><strong class="mt-1 block text-lg text-rose-300">{{
                                inventorySummary.quarantine_location_count || 0
                            }}</strong>
                        </div>
                        <div
                            class="rounded-lg border border-border bg-muted/20 p-3"
                        >
                            <span class="text-muted-foreground"
                                >Vị trí hoạt động</span
                            ><strong class="mt-1 block text-lg text-sky-300">{{
                                inventorySummary.location_count ||
                                locations.length
                            }}</strong>
                        </div>
                        <div
                            class="rounded-lg border border-border bg-muted/20 p-3"
                        >
                            <span class="text-muted-foreground"
                                >FEFO tuân thủ</span
                            ><strong class="mt-1 block text-lg text-indigo-300"
                                >{{
                                    warehouseKpi.fefo_compliance ?? 100
                                }}%</strong
                            >
                        </div>
                        <div
                            class="rounded-lg border border-border bg-muted/20 p-3"
                        >
                            <span class="text-muted-foreground">OTIF tháng</span
                            ><strong class="mt-1 block text-lg text-emerald-300"
                                >{{ warehouseKpi.otif_percent ?? 100 }}%</strong
                            >
                        </div>
                        <div
                            class="col-span-2 rounded-lg border border-indigo-500/20 bg-indigo-950/10 p-3 text-indigo-200"
                        >
                            {{ receivingSummary?.today || 0 }} phiếu nhập hôm
                            nay ·
                            {{ receivingSummary?.pending_review || 0 }} phiếu
                            cần kiểm tra ·
                            {{ receivingSummary?.discrepancy_quantity || 0 }}
                            đơn vị chênh lệch.
                        </div>
                    </CardContent>
                </Card>
            </div>
        </section>

        <!-- Owner supply intelligence -->
        <section v-if="false" class="space-y-4">
            <div
                class="flex flex-col justify-between gap-2 sm:flex-row sm:items-end"
            >
                <div>
                    <p
                        class="flex items-center gap-2 text-xs font-bold tracking-[0.16em] text-indigo-600 uppercase"
                    >
                        <Activity class="h-4 w-4" /> Điều hành nhập hàng
                    </p>
                    <h2
                        class="mt-1 text-xl font-bold tracking-tight text-foreground"
                    >
                        Chủ doanh nghiệp cần biết gì hôm nay?
                    </h2>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Phân tích từ {{ analytics.period_days }} ngày yêu cầu
                        cấp phát gần nhất; dự báo nhu cầu 7 ngày tới.
                    </p>
                </div>
                <span class="text-[11px] text-muted-foreground">
                    Cập nhật {{ formatDate(analytics.generated_at) }}
                </span>
            </div>

            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <Card class="border-indigo-500/20 bg-indigo-950/10 shadow-sm">
                    <CardContent class="p-4">
                        <div class="flex items-start justify-between gap-2">
                            <p
                                class="text-[11px] font-bold tracking-wide text-indigo-300 uppercase"
                            >
                                Đơn gửi hôm nay
                            </p>
                            <CalendarDays class="h-4 w-4 text-indigo-400" />
                        </div>
                        <p class="mt-2 text-2xl font-bold text-indigo-100">
                            {{ analytics.summary.today_requests }}
                        </p>
                        <p class="mt-1 text-[11px] text-indigo-200/80">
                            {{ formatQuantity(analytics.summary.today_items) }}
                            đơn vị ·
                            {{ formatCurrency(analytics.summary.today_value) }}
                        </p>
                    </CardContent>
                </Card>
                <Card class="border-amber-500/20 bg-amber-950/10 shadow-sm">
                    <CardContent class="p-4">
                        <div class="flex items-start justify-between gap-2">
                            <p
                                class="text-[11px] font-bold tracking-wide text-amber-300 uppercase"
                            >
                                Cần xử lý
                            </p>
                            <Clock class="h-4 w-4 text-amber-400" />
                        </div>
                        <p class="mt-2 text-2xl font-bold text-amber-100">
                            {{ analytics.summary.today_pending }}
                        </p>
                        <p class="mt-1 text-[11px] text-amber-200/80">
                            đơn đang chờ Kho Tổng duyệt
                        </p>
                    </CardContent>
                </Card>
                <Card class="border-emerald-500/20 bg-emerald-950/10 shadow-sm">
                    <CardContent class="p-4">
                        <div class="flex items-start justify-between gap-2">
                            <p
                                class="text-[11px] font-bold tracking-wide text-emerald-300 uppercase"
                            >
                                Nhu cầu 7 ngày
                            </p>
                            <TrendingUp class="h-4 w-4 text-emerald-400" />
                        </div>
                        <p class="mt-2 text-2xl font-bold text-emerald-100">
                            {{ analytics.summary.last7_requests }}
                        </p>
                        <p class="mt-1 text-[11px] text-emerald-200/80">
                            {{ formatQuantity(analytics.summary.last7_items) }}
                            đơn vị · TB
                            {{ analytics.summary.average_daily_requests }}
                            đơn/ngày
                        </p>
                    </CardContent>
                </Card>
                <Card class="border-rose-500/20 bg-rose-950/10 shadow-sm">
                    <CardContent class="p-4">
                        <div class="flex items-start justify-between gap-2">
                            <p
                                class="text-[11px] font-bold tracking-wide text-rose-300 uppercase"
                            >
                                Cần nhập gấp
                            </p>
                            <AlertTriangle class="h-4 w-4 text-rose-400" />
                        </div>
                        <p class="mt-2 text-2xl font-bold text-rose-100">
                            {{ analytics.summary.urgent_recommendations }}
                        </p>
                        <p class="mt-1 text-[11px] text-rose-200/80">
                            nguyên liệu có rủi ro thiếu trong 7 ngày
                        </p>
                    </CardContent>
                </Card>
            </div>

            <div class="grid gap-4 lg:grid-cols-3">
                <Card class="border-border shadow-sm lg:col-span-2">
                    <CardHeader class="border-b border-border bg-muted/20 py-4">
                        <CardTitle
                            class="flex items-center gap-2 text-sm font-bold text-foreground"
                        >
                            <BarChart3 class="h-4 w-4 text-indigo-600" /> Nhịp
                            gửi đơn 7 ngày qua
                        </CardTitle>
                        <CardDescription class="text-xs"
                            >Số đơn và tổng số lượng chi nhánh yêu cầu theo
                            ngày.</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="p-4">
                        <div
                            class="flex h-44 items-end gap-2 border-b border-border px-1 pb-1 sm:gap-4"
                        >
                            <div
                                v-for="day in analytics.daily"
                                :key="day.date"
                                class="group flex h-full flex-1 flex-col items-center justify-end gap-2"
                            >
                                <div
                                    class="relative flex h-full w-full max-w-12 items-end justify-center"
                                >
                                    <span
                                        class="absolute -top-5 text-[10px] font-semibold text-muted-foreground opacity-0 transition group-hover:opacity-100"
                                    >
                                        {{ day.requests }} đơn
                                    </span>
                                    <div
                                        class="w-full rounded-t-lg bg-gradient-to-t from-indigo-600 to-violet-400 transition group-hover:from-indigo-700 group-hover:to-violet-500"
                                        :style="{
                                            height: `${Math.max(8, (Number(day.items || 0) / maxDailyItems) * 100)}%`,
                                        }"
                                    ></div>
                                </div>
                                <span
                                    class="text-[10px] font-semibold text-muted-foreground"
                                    >{{ day.label }}</span
                                >
                            </div>
                        </div>
                        <div
                            class="mt-3 flex flex-wrap items-center gap-x-5 gap-y-1 text-[11px] text-muted-foreground"
                        >
                            <span
                                ><strong class="text-foreground">{{
                                    formatCurrency(
                                        analytics.summary.last7_value,
                                    )
                                }}</strong>
                                giá trị yêu cầu</span
                            >
                            <span
                                ><strong class="text-foreground">{{
                                    formatQuantity(
                                        analytics.summary.last7_items,
                                    )
                                }}</strong>
                                đơn vị nguyên liệu</span
                            >
                            <span
                                class="inline-flex items-center gap-1 text-indigo-600"
                                ><ArrowUpRight class="h-3.5 w-3.5" /> So sánh
                                theo ngày</span
                            >
                        </div>
                    </CardContent>
                </Card>

                <Card class="border-amber-500/20 bg-amber-950/10 shadow-sm">
                    <CardHeader class="border-b border-amber-900/40 py-4">
                        <CardTitle
                            class="flex items-center gap-2 text-sm font-bold text-amber-100"
                        >
                            <Lightbulb class="h-4 w-4 text-amber-500" /> Lời
                            khuyên vận hành
                        </CardTitle>
                        <CardDescription class="text-xs"
                            >Các điểm cần chủ doanh nghiệp hoặc quản lý Kho Tổng
                            quyết định.</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="space-y-3 p-4">
                        <div
                            v-for="insight in analytics.insights"
                            :key="`${insight.type}-${insight.title}`"
                            class="rounded-xl border border-amber-900/40 bg-amber-950/20 p-3"
                        >
                            <p
                                class="flex items-center gap-2 text-xs font-bold text-foreground"
                            >
                                <span
                                    class="h-2 w-2 rounded-full"
                                    :class="
                                        insight.type === 'danger'
                                            ? 'bg-rose-500'
                                            : insight.type === 'warning'
                                              ? 'bg-amber-500'
                                              : insight.type === 'success'
                                                ? 'bg-emerald-500'
                                                : 'bg-indigo-500'
                                    "
                                ></span>
                                {{ insight.title }}
                            </p>
                            <p
                                class="mt-1 text-[11px] leading-relaxed text-muted-foreground"
                            >
                                {{ insight.message }}
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <Card class="border-border shadow-sm">
                    <CardHeader class="border-b border-border bg-muted/20 py-4">
                        <CardTitle
                            class="flex items-center gap-2 text-sm font-bold text-foreground"
                        >
                            <CalendarDays class="h-4 w-4 text-indigo-600" /> Đơn
                            gửi về Kho hôm nay
                        </CardTitle>
                        <CardDescription class="text-xs"
                            >Bấm vào từng đơn để duyệt, điều chỉnh hoặc xuất
                            giao.</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="p-0">
                        <div
                            v-if="analytics.today_requests.length === 0"
                            class="p-8 text-center text-xs text-muted-foreground"
                        >
                            Chưa có chi nhánh gửi đơn mới hôm nay.
                        </div>
                        <div v-else class="divide-y divide-border">
                            <button
                                v-for="request in analytics.today_requests"
                                :key="request.id"
                                type="button"
                                class="flex w-full items-center justify-between gap-3 p-3 text-left transition hover:bg-indigo-950/20"
                                @click="openAnalyticsRequest(request.id)"
                            >
                                <span class="min-w-0">
                                    <span class="flex items-center gap-2">
                                        <span
                                            class="font-mono text-xs font-bold text-indigo-600"
                                            >{{ request.request_code }}</span
                                        >
                                        <span
                                            :class="[
                                                'rounded-full border px-2 py-0.5 text-[10px] font-semibold',
                                                getStatusBadge(request.status)
                                                    .color,
                                            ]"
                                        >
                                            {{
                                                getStatusBadge(request.status)
                                                    .label
                                            }}
                                        </span>
                                    </span>
                                    <span
                                        class="mt-1 block truncate text-[11px] text-muted-foreground"
                                        >{{ request.branch_name }} ·
                                        {{ request.items }} nguyên liệu</span
                                    >
                                </span>
                                <span class="shrink-0 text-right">
                                    <span
                                        class="block text-xs font-bold text-emerald-700"
                                        >{{
                                            formatCurrency(request.value)
                                        }}</span
                                    >
                                    <span
                                        class="mt-1 block text-[10px] text-muted-foreground"
                                        >{{
                                            formatDate(request.created_at)
                                        }}</span
                                    >
                                </span>
                            </button>
                        </div>
                    </CardContent>
                </Card>

                <Card class="border-border shadow-sm">
                    <CardHeader class="border-b border-border bg-muted/20 py-4">
                        <CardTitle
                            class="flex items-center gap-2 text-sm font-bold text-foreground"
                        >
                            <TrendingUp class="h-4 w-4 text-emerald-600" />
                            Nguyên liệu được yêu cầu nhiều
                        </CardTitle>
                        <CardDescription class="text-xs"
                            >Xếp theo tổng số lượng trong
                            {{ analytics.period_days }} ngày gần
                            nhất.</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="p-0">
                        <div
                            v-if="analytics.top_ingredients.length === 0"
                            class="p-8 text-center text-xs text-muted-foreground"
                        >
                            Chưa đủ dữ liệu để phân tích nhu cầu.
                        </div>
                        <div v-else class="divide-y divide-border">
                            <div
                                v-for="(
                                    item, index
                                ) in analytics.top_ingredients"
                                :key="item.ingredient_id"
                                class="flex items-center gap-3 p-3"
                            >
                                <span
                                    class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-indigo-950/40 text-xs font-bold text-indigo-300"
                                    >{{ Number(index) + 1 }}</span
                                >
                                <div class="min-w-0 flex-1">
                                    <p
                                        class="truncate text-xs font-bold text-foreground"
                                    >
                                        {{ item.name }}
                                    </p>
                                    <p
                                        class="mt-0.5 text-[10px] text-muted-foreground"
                                    >
                                        {{ item.request_count }} đơn ·
                                        {{ formatCurrency(item.total_value) }}
                                    </p>
                                </div>
                                <p
                                    class="shrink-0 text-right text-xs font-bold text-foreground"
                                >
                                    {{ formatQuantity(item.total_quantity) }}
                                    {{ item.unit_symbol }}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Card class="border-rose-500/20 shadow-sm">
                <CardHeader
                    class="border-b border-rose-900/40 bg-rose-950/10 py-4"
                >
                    <div
                        class="flex flex-col justify-between gap-2 sm:flex-row sm:items-center"
                    >
                        <div>
                            <CardTitle
                                class="flex items-center gap-2 text-sm font-bold text-foreground"
                            >
                                <AlertTriangle class="h-4 w-4 text-rose-600" />
                                Dự báo và đề xuất nhập Kho Tổng
                            </CardTitle>
                            <CardDescription class="text-xs"
                                >Ước tính dựa trên nhu cầu 28 ngày, đơn đang chờ
                                cấp và mức tồn tối thiểu.</CardDescription
                            >
                        </div>
                        <span class="text-[11px] font-semibold text-rose-300"
                            >Không tự động đặt hàng · cần xác nhận của chủ doanh
                            nghiệp</span
                        >
                    </div>
                </CardHeader>
                <CardContent class="p-0">
                    <div
                        v-if="analytics.recommendations.length === 0"
                        class="p-8 text-center text-xs text-muted-foreground"
                    >
                        Tồn kho hiện đáp ứng được nhu cầu dự kiến.
                    </div>
                    <div v-else class="overflow-x-auto">
                        <table class="w-full min-w-[760px] text-left text-xs">
                            <thead
                                class="border-b border-border bg-muted/50 font-semibold text-muted-foreground"
                            >
                                <tr>
                                    <th class="p-3 pl-4">Nguyên liệu</th>
                                    <th class="p-3 text-right">Tồn hiện tại</th>
                                    <th class="p-3 text-right">Đơn đang chờ</th>
                                    <th class="p-3 text-right">
                                        Dự báo 7 ngày
                                    </th>
                                    <th class="p-3 text-right">
                                        Nên nhập thêm
                                    </th>
                                    <th class="p-3">Khuyến nghị</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr
                                    v-for="item in analytics.recommendations"
                                    :key="item.ingredient_id"
                                    class="transition hover:bg-rose-950/20"
                                >
                                    <td class="p-3 pl-4">
                                        <p class="font-bold text-foreground">
                                            {{ item.name }}
                                        </p>
                                        <p
                                            class="mt-0.5 text-[10px] text-muted-foreground"
                                        >
                                            {{ item.sku || 'Chưa có SKU' }} ·
                                            {{
                                                item.coverage_days === null
                                                    ? 'chưa đủ lịch sử'
                                                    : `đủ khoảng ${item.coverage_days} ngày`
                                            }}
                                        </p>
                                    </td>
                                    <td
                                        class="p-3 text-right font-semibold text-foreground"
                                    >
                                        {{ formatQuantity(item.current_stock) }}
                                        {{ item.unit_symbol }}
                                    </td>
                                    <td class="p-3 text-right text-amber-700">
                                        {{ formatQuantity(item.open_quantity) }}
                                    </td>
                                    <td class="p-3 text-right text-indigo-700">
                                        {{ formatQuantity(item.forecast_7d) }}
                                    </td>
                                    <td
                                        class="p-3 text-right font-bold text-rose-700"
                                    >
                                        {{
                                            formatQuantity(
                                                item.recommended_quantity,
                                            )
                                        }}
                                        {{ item.unit_symbol
                                        }}<span
                                            class="block text-[10px] font-normal text-slate-400"
                                            >{{
                                                formatCurrency(
                                                    item.estimated_cost,
                                                )
                                            }}</span
                                        >
                                    </td>
                                    <td class="p-3">
                                        <span
                                            :class="[
                                                'rounded-full border px-2 py-1 text-[10px] font-semibold',
                                                getPriorityBadge(item.priority)
                                                    .color,
                                            ]"
                                            >{{
                                                getPriorityBadge(item.priority)
                                                    .label
                                            }}</span
                                        >
                                        <p
                                            class="mt-1 max-w-xs text-[10px] leading-relaxed text-muted-foreground"
                                        >
                                            {{ item.advice }}
                                        </p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </section>

        <Card
            v-if="false && canManageWarehouse"
            class="border-border shadow-sm"
        >
            <CardHeader class="border-b border-border bg-muted/20 py-4">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <CardTitle class="text-base font-bold text-foreground"
                            >Đơn giá nguyên liệu đồng bộ toàn chuỗi</CardTitle
                        >
                        <CardDescription class="text-xs"
                            >Kho Tổng thiết lập giá dùng để tính giá trị cấp
                            phát cho mọi chi nhánh.</CardDescription
                        >
                    </div>
                    <Button
                        @click="saveIngredientPrices"
                        size="sm"
                        :disabled="isSavingPrices"
                        class="gap-1.5 bg-indigo-600 text-xs font-semibold text-white hover:bg-indigo-700"
                    >
                        <Save class="h-4 w-4" /> Lưu đơn giá
                    </Button>
                </div>
            </CardHeader>
            <CardContent class="p-0">
                <div class="max-h-72 overflow-y-auto">
                    <table class="w-full text-left text-xs">
                        <thead
                            class="sticky top-0 border-b border-border bg-muted/50 font-semibold text-muted-foreground"
                        >
                            <tr>
                                <th class="p-3 pl-4">Nguyên liệu</th>
                                <th class="p-3">SKU</th>
                                <th class="p-3 text-center">Đơn vị</th>
                                <th class="p-3 pr-4 text-right">
                                    Đơn giá Kho Tổng
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-if="priceRows.length === 0">
                                <td
                                    colspan="4"
                                    class="p-6 text-center text-muted-foreground"
                                >
                                    Chưa có nguyên liệu để thiết lập đơn giá.
                                </td>
                            </tr>
                            <tr
                                v-for="row in priceRows"
                                :key="row.ingredient_id"
                                class="transition hover:bg-muted/30"
                            >
                                <td
                                    class="p-3 pl-4 font-semibold text-foreground"
                                >
                                    {{ row.name }}
                                </td>
                                <td class="p-3 font-mono text-muted-foreground">
                                    {{ row.sku || '-' }}
                                </td>
                                <td
                                    class="p-3 text-center text-muted-foreground"
                                >
                                    {{ row.unit_symbol }}
                                </td>
                                <td class="p-3 pr-4">
                                    <div
                                        class="ml-auto flex max-w-[190px] items-center gap-2"
                                    >
                                        <DollarSign
                                            class="h-4 w-4 text-emerald-600"
                                        />
                                        <Input
                                            v-model.number="row.average_cost"
                                            type="number"
                                            min="0"
                                            step="1000"
                                            class="h-8 text-right text-xs font-bold text-emerald-300"
                                        />
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>



        <!-- Requests Table -->
        <Card class="border-border shadow-sm">
            <CardHeader
                class="border-b border-border bg-muted/20 py-4 px-4 sm:px-6"
            >
                <div
                    class="flex flex-col gap-3.5 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <CardTitle class="text-base font-bold text-foreground">
                            Danh sách Đơn xin Cấp phát từ các Chi nhánh
                        </CardTitle>
                        <CardDescription class="mt-0.5 text-xs">
                            Duyệt đơn, chốt số lượng thực xuất và tạo lệnh xuất
                            kho cho chi nhánh
                        </CardDescription>
                    </div>

                    <!-- Search & Branch Filter Controls directly on Table Header -->
                    <div class="flex flex-col sm:flex-row items-center gap-2.5">
                        <select
                            v-model="selectedBranchFilter"
                            class="h-9 w-full sm:w-auto rounded-xl border border-input bg-background px-3 py-1.5 text-xs font-semibold text-foreground shadow-xs transition focus:ring-2 focus:ring-primary/20 focus:outline-none"
                        >
                            <option value="all">🏢 Tất cả chi nhánh đặt</option>
                            <option
                                v-for="b in branches"
                                :key="b.id"
                                :value="b.id"
                            >
                                {{ b.name }}
                            </option>
                        </select>

                        <div class="relative w-full sm:w-72">
                            <Search
                                class="absolute top-2.5 left-3 h-4 w-4 text-muted-foreground"
                            />
                            <Input
                                v-model="searchQuery"
                                placeholder="Tìm mã đơn, tên chi nhánh..."
                                class="h-9 rounded-xl border-input bg-background pl-9 pr-7 text-xs shadow-xs focus-visible:ring-2 focus-visible:ring-primary/20"
                            />
                            <button
                                v-if="searchQuery"
                                @click="searchQuery = ''"
                                class="absolute top-2.5 right-2.5 flex h-4 w-4 items-center justify-center rounded-full text-[10px] text-muted-foreground hover:bg-muted hover:text-foreground"
                            >
                                ✕
                            </button>
                        </div>
                    </div>
                </div>
            </CardHeader>
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead
                            class="border-b border-border bg-muted/50 font-semibold text-muted-foreground"
                        >
                            <tr>
                                <th class="p-3 pl-4">Mã Đơn</th>
                                <th class="p-3">Chi Nhánh Yêu Cầu</th>
                                <th class="p-3">Số Mặt Hàng</th>
                                <th class="p-3">Tổng Giá Trị</th>
                                <th class="p-3">Trạng Thái</th>
                                <th class="p-3">Người Lập Đơn</th>
                                <th class="p-3">Ngày Tạo</th>
                                <th class="p-3 pr-4 text-right">Hành Động</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-if="filteredRequests.length === 0">
                                <td
                                    colspan="8"
                                    class="p-8 text-center text-muted-foreground"
                                >
                                    Không tìm thấy đơn xin cấp phát nào phù hợp.
                                </td>
                            </tr>
                            <tr
                                v-for="req in filteredRequests"
                                :key="req.id"
                                class="transition hover:bg-muted/30"
                            >
                                <td
                                    class="p-3 pl-4 font-mono font-bold text-indigo-600"
                                >
                                    {{ req.request_code }}
                                </td>
                                <td class="p-3 font-semibold text-foreground">
                                    {{ req.to_branch?.name }}
                                </td>
                                <td class="p-3 text-muted-foreground">
                                    {{ req.items?.length || 0 }} nguyên liệu
                                </td>
                                <td class="p-3 font-semibold text-emerald-700">
                                    {{ formatCurrency(req.total_amount) }}
                                </td>
                                <td class="p-3">
                                    <span
                                        :class="[
                                            'rounded-full border px-2.5 py-1 text-[11px] font-medium',
                                            getStatusBadge(req.status).color,
                                        ]"
                                    >
                                        {{ getStatusBadge(req.status).label }}
                                    </span>
                                </td>
                                <td class="p-3 text-muted-foreground">
                                    {{ req.creator?.name || '-' }}
                                </td>
                                <td class="p-3 text-muted-foreground">
                                    {{ formatDate(req.created_at) }}
                                </td>
                                <td class="p-3 pr-4 text-right">
                                    <Button
                                        @click="openDetailModal(req)"
                                        size="sm"
                                        variant="outline"
                                        class="h-8 gap-1.5 text-xs"
                                    >
                                        <Eye class="h-3.5 w-3.5" /> Chi tiết &
                                        Xử lý
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <!-- Detail & Action Modal -->
        <Teleport to="body">
            <div
                v-if="isDetailModalOpen && selectedRequest"
                class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm"
            >
                <div
                    class="flex max-h-[92vh] w-full max-w-4xl lg:max-w-5xl flex-col overflow-hidden rounded-2xl border border-border bg-card shadow-2xl"
                >
                    <!-- Modal Header -->
                    <div
                        class="flex items-center justify-between border-b bg-slate-900 p-5 text-white"
                    >
                        <div>
                            <div class="flex items-center gap-2">
                                <span
                                    class="font-mono text-lg font-bold text-indigo-300"
                                    >{{ selectedRequest.request_code }}</span
                                >
                                <span
                                    :class="[
                                        'rounded-full border px-2.5 py-0.5 text-xs font-semibold',
                                        getStatusBadge(selectedRequest.status)
                                            .color,
                                    ]"
                                >
                                    {{
                                        getStatusBadge(selectedRequest.status)
                                            .label
                                    }}
                                </span>
                            </div>
                            <p class="mt-1 text-xs text-slate-300">
                                Chi nhánh nhận:
                                <strong class="text-white">{{
                                    selectedRequest.to_branch?.name
                                }}</strong>
                                | Lập bởi: {{ selectedRequest.creator?.name }}
                            </p>
                        </div>
                        <button
                            @click="isDetailModalOpen = false"
                            class="rounded-lg p-1 text-slate-400 hover:text-white"
                        >
                            <X class="h-6 w-6" />
                        </button>
                    </div>

                    <!-- Modal Body -->
                    <div class="flex-1 space-y-6 overflow-y-auto p-6 text-xs">
                        <!-- Notes -->
                        <div
                            v-if="selectedRequest.notes"
                            class="flex items-start gap-2 rounded-xl border border-amber-900/50 bg-amber-950/20 p-3 text-amber-200"
                        >
                            <AlertCircle
                                class="mt-0.5 h-4 w-4 shrink-0 text-amber-600"
                            />
                            <div>
                                <strong>Ghi chú đơn hàng:</strong>
                                <p class="mt-0.5 whitespace-pre-line">
                                    {{ selectedRequest.notes }}
                                </p>
                            </div>
                        </div>

                        <!-- Items List -->
                        <div
                            v-if="
                                [
                                    'dispatched',
                                    'partial_received',
                                    'disputed',
                                    'completed',
                                ].includes(selectedRequest.status)
                            "
                            class="grid grid-cols-2 gap-3 sm:grid-cols-4"
                        >
                            <div
                                class="rounded-lg border border-border bg-muted/20 p-3"
                            >
                                <span class="text-muted-foreground"
                                    >Đã xuất</span
                                ><strong
                                    class="mt-1 block text-lg text-indigo-300"
                                    >{{
                                        formatQuantity(
                                            getRequestQuantitySummary(
                                                selectedRequest,
                                            ).dispatched,
                                        )
                                    }}</strong
                                >
                            </div>
                            <div
                                class="rounded-lg border border-border bg-muted/20 p-3"
                            >
                                <span class="text-muted-foreground"
                                    >Đã nhận</span
                                ><strong
                                    class="mt-1 block text-lg text-emerald-300"
                                    >{{
                                        formatQuantity(
                                            getRequestQuantitySummary(
                                                selectedRequest,
                                            ).received,
                                        )
                                    }}</strong
                                >
                            </div>
                            <div
                                class="rounded-lg border border-border bg-muted/20 p-3"
                            >
                                <span class="text-muted-foreground"
                                    >Còn thiếu</span
                                ><strong
                                    class="mt-1 block text-lg text-orange-300"
                                    >{{
                                        formatQuantity(
                                            getRequestQuantitySummary(
                                                selectedRequest,
                                            ).shortage,
                                        )
                                    }}</strong
                                >
                            </div>
                            <div
                                class="rounded-lg border border-border bg-muted/20 p-3"
                            >
                                <span class="text-muted-foreground"
                                    >Người nhận</span
                                ><strong
                                    class="mt-1 block truncate text-xs text-foreground"
                                    >{{
                                        selectedRequest.receiver?.name ||
                                        'Chưa xác nhận'
                                    }}</strong
                                >
                            </div>
                        </div>

                        <div>
                            <h4
                                class="mb-3 flex items-center justify-between text-sm font-bold text-foreground"
                            >
                                <span>Danh sách Nguyên Liệu Yêu Cầu</span>
                                <span class="text-xs text-muted-foreground"
                                    >Giá niêm yết đồng bộ tại Kho Tổng</span
                                >
                            </h4>

                            <div class="overflow-hidden rounded-xl border">
                                <table class="w-full text-left">
                                    <thead
                                        class="border-b border-border bg-muted/50 font-semibold text-muted-foreground"
                                    >
                                        <tr>
                                            <th class="p-3">Nguyên Liệu</th>
                                            <th class="p-3 text-center">
                                                Đơn Vị
                                            </th>
                                            <th class="p-3 text-right">
                                                Chi Nhánh Xin
                                            </th>
                                            <th class="p-3 text-right">
                                                Kho Tổng Duyệt
                                            </th>
                                            <th class="p-3 text-right">
                                                Đơn Giá Kho
                                            </th>
                                            <th class="p-3 pr-4 text-right">
                                                Thành Tiền
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-border">
                                        <tr
                                            v-for="item in selectedRequest.items"
                                            :key="item.id"
                                            class="hover:bg-muted/30"
                                        >
                                            <td
                                                class="p-3 font-semibold text-foreground"
                                            >
                                                {{ item.ingredient?.name }}
                                            </td>
                                            <td
                                                class="p-3 text-center font-mono text-muted-foreground"
                                            >
                                                {{ item.unit_symbol || 'kg' }}
                                            </td>
                                            <td
                                                class="p-3 text-right font-bold text-foreground"
                                            >
                                                {{ item.requested_quantity }}
                                            </td>
                                            <td class="p-3 text-right">
                                                <input
                                                    v-if="
                                                        selectedRequest.status ===
                                                            'pending' &&
                                                        canApproveRequests
                                                    "
                                                    type="number"
                                                    step="0.1"
                                                    min="0"
                                                    v-model.number="
                                                        item.approved_quantity
                                                    "
                                                    class="w-20 rounded border border-input bg-background px-2 py-1 text-right font-bold text-indigo-300 focus:border-indigo-500 focus:outline-none"
                                                />
                                                <span
                                                    v-else
                                                    class="font-bold text-indigo-600"
                                                    >{{
                                                        item.approved_quantity ??
                                                        item.requested_quantity
                                                    }}</span
                                                >
                                            </td>
                                            <td
                                                class="p-3 text-right text-muted-foreground"
                                            >
                                                {{
                                                    formatCurrency(
                                                        item.unit_cost,
                                                    )
                                                }}
                                            </td>
                                            <td
                                                class="p-3 pr-4 text-right font-bold text-emerald-700"
                                            >
                                                {{
                                                    formatCurrency(
                                                        (item.approved_quantity ??
                                                            item.requested_quantity) *
                                                            item.unit_cost,
                                                    )
                                                }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Modal Footer Actions -->
                    <div
                        class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-t border-border bg-muted/20 p-4"
                    >
                        <div class="text-xs text-muted-foreground shrink-0">
                            Tổng giá trị cấp phát:
                            <strong
                                class="ml-1 text-sm font-bold text-emerald-600 dark:text-emerald-400"
                                >{{
                                    formatCurrency(selectedRequest.total_amount)
                                }}</strong
                            >
                        </div>

                        <div class="flex flex-wrap items-center justify-end gap-2">
                            <Button
                                v-if="
                                    [
                                        'pending',
                                        'approved',
                                        'preparing',
                                    ].includes(selectedRequest.status) &&
                                    canManageWarehouse
                                "
                                @click="cancelRequest"
                                variant="outline"
                                size="sm"
                                :disabled="isProcessing"
                                class="gap-1.5 rounded-xl h-9 px-3 text-xs text-rose-500 hover:text-rose-600 border-rose-500/20 hover:bg-rose-500/10"
                            >
                                Hủy đơn
                            </Button>
                            <Button
                                v-if="
                                    ['partial_received', 'disputed'].includes(
                                        selectedRequest.status,
                                    ) && canManageWarehouse
                                "
                                @click="createBackorder"
                                variant="outline"
                                size="sm"
                                :disabled="isProcessing"
                                class="gap-1.5 rounded-xl h-9 px-3 text-xs text-amber-500 hover:text-amber-600 border-amber-500/20 hover:bg-amber-500/10"
                            >
                                Tạo giao bù
                            </Button>
                            <Button
                                v-if="
                                    selectedRequest.status === 'pending' &&
                                    canApproveRequests
                                "
                                @click="rejectRequest"
                                variant="destructive"
                                size="sm"
                                :disabled="isProcessing"
                                class="gap-1.5 rounded-xl h-9 px-3 text-xs"
                            >
                                <XCircle class="h-4 w-4" /> Từ chối
                            </Button>

                            <Button
                                v-if="
                                    selectedRequest.status === 'pending' &&
                                    canApproveRequests
                                "
                                @click="approveRequest"
                                size="sm"
                                :disabled="isProcessing"
                                class="gap-1.5 rounded-xl h-9 px-3 bg-indigo-600 text-xs font-bold text-white shadow-xs hover:bg-indigo-700"
                            >
                                <Check class="h-4 w-4" /> Duyệt đơn hàng
                            </Button>

                            <Button
                                v-if="
                                    canManageWarehouse &&
                                    warehouseStaff.length > 0 &&
                                    (selectedRequest.status === 'approved' ||
                                        selectedRequest.status === 'preparing')
                                "
                                @click="
                                    openTaskModal(selectedRequest, 'picking')
                                "
                                size="sm"
                                variant="outline"
                                class="gap-1.5 rounded-xl h-9 px-3 text-xs shadow-xs"
                                :class="
                                    getAssignedStaffName(selectedRequest)
                                        ? 'border-emerald-500/50 bg-emerald-500/10 font-bold text-emerald-600 dark:text-emerald-300 hover:bg-emerald-500/20'
                                        : 'text-indigo-600 dark:text-indigo-300 border-indigo-500/30'
                                "
                            >
                                <UserCheck class="h-4 w-4" />
                                {{
                                    getAssignedStaffName(selectedRequest)
                                        ? `Đã giao: ${getAssignedStaffName(selectedRequest)}`
                                        : 'Giao người soạn'
                                }}
                            </Button>



                            <!-- Nút 2: Duyệt xuất (khi status == 'prepared' hoặc 'preparing') -->
                            <Button
                                v-if="
                                    (selectedRequest.status === 'prepared' ||
                                        selectedRequest.status ===
                                            'preparing') &&
                                    canApproveRequests
                                "
                                @click="approveDispatchManager"
                                size="sm"
                                :disabled="isProcessing"
                                class="gap-1.5 rounded-xl h-9 px-3 bg-indigo-600 text-xs font-bold text-white shadow-xs hover:bg-indigo-700"
                            >
                                <CheckCircle2 class="h-4 w-4" /> Duyệt Xuất
                            </Button>

                            <!-- Nút 3: Thật xuất kho & bàn giao -->
                            <Button
                                v-if="
                                    selectedRequest.status ===
                                        'dispatch_pending_approval' &&
                                    canDispatchRequests
                                "
                                @click="openDispatchModal(selectedRequest)"
                                size="sm"
                                :disabled="isProcessing"
                                class="gap-1.5 rounded-xl h-9 px-3 bg-purple-600 text-xs font-bold text-white shadow-xs hover:bg-purple-700"
                            >
                                <Truck class="h-4 w-4" /> Xuất Kho Bàn Giao
                            </Button>

                            <Button
                                v-if="
                                    canManageWarehouse &&
                                    warehouseStaff.length > 0 &&
                                    isTransporterAssignmentOnly
                                "
                                @click="openDispatchModal(selectedRequest)"
                                size="sm"
                                variant="outline"
                                :disabled="isProcessing"
                                class="gap-1.5 rounded-xl h-9 border-amber-500/40 px-3 text-xs font-bold text-amber-700 hover:bg-amber-500/10 dark:text-amber-300"
                            >
                                <UserCheck class="h-4 w-4" /> Gán nhân viên giao
                            </Button>

                            <Button
                                v-if="
                                    canManageWarehouse &&
                                    warehouseStaff.length > 0 &&
                                    selectedRequest.status ===
                                        'dispatch_pending_approval'
                                "
                                @click="
                                    openTaskModal(selectedRequest, 'handover')
                                "
                                size="sm"
                                variant="outline"
                                class="gap-1.5 rounded-xl h-9 px-3 text-xs shadow-xs"
                                :class="
                                    getAssignedStaffName(
                                        selectedRequest,
                                        'handover',
                                    )
                                        ? 'border-emerald-500/50 bg-emerald-500/10 font-bold text-emerald-600 dark:text-emerald-300 hover:bg-emerald-500/20'
                                        : 'text-indigo-600 dark:text-indigo-300 border-indigo-500/30'
                                "
                            >
                                <UserCheck class="h-4 w-4" />
                                {{
                                    getAssignedStaffName(
                                        selectedRequest,
                                        'handover',
                                    )
                                        ? `Đã giao: ${getAssignedStaffName(selectedRequest, 'handover')}`
                                        : 'Giao người bàn giao'
                                }}
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ── MODAL XÁC NHẬN XUẤT KHO BÀN GIAO ── -->
        <Teleport to="body">
            <div
                v-if="isDispatchModalOpen && selectedRequest"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm"
            >
                <div
                    class="flex max-h-[90vh] w-full max-w-lg flex-col overflow-hidden rounded-xl border border-purple-500/30 bg-card shadow-2xl"
                >
                    <div
                        class="flex items-center justify-between border-b bg-purple-950/50 px-6 py-4"
                    >
                        <div class="flex items-center gap-2">
                            <Truck class="h-5 w-5 text-purple-400" />
                            <h3 class="text-base font-bold text-foreground">
                                {{ isTransporterAssignmentOnly ? 'Gán Nhân Viên Giao —' : 'Bàn Giao Xuất Kho —' }}
                                {{ selectedRequest.request_code }}
                            </h3>
                        </div>
                        <button
                            @click="isDispatchModalOpen = false"
                            class="text-muted-foreground hover:text-foreground"
                        >
                            <X class="h-5 w-5" />
                        </button>
                    </div>

                    <div class="space-y-4 overflow-y-auto p-6 text-xs">
                        <div
                            class="rounded-lg border border-purple-500/30 bg-purple-500/10 p-3 text-purple-200"
                        >
                            <div class="font-bold">
                                Chi nhánh nhận:
                                {{
                                    selectedRequest.to_branch?.name ||
                                    selectedRequest.toBranch?.name ||
                                    'Chi nhánh'
                                }}
                            </div>
                            <div class="mt-1 text-[11px] text-muted-foreground">
                                Tổng giá trị:
                                {{
                                    formatCurrency(selectedRequest.total_amount)
                                }}
                                ·
                                {{ selectedRequest.items?.length || 0 }} nguyên
                                liệu
                            </div>
                        </div>

                        <div v-if="!isTransporterAssignmentOnly">
                            <label
                                class="mb-1 block font-semibold text-muted-foreground"
                                >Chọn Chuyến Xe Logistics (nếu gom chuyến
                                xe)</label
                            >
                            <select
                                v-model="dispatchForm.manifest_id"
                                class="w-full rounded-lg border border-input bg-background px-3 py-2 text-foreground"
                            >
                                <option value="">
                                    -- Tự vận chuyển lẻ / Chọn chuyến xe --
                                </option>
                                <option
                                    v-for="m in manifests"
                                    :key="m.id"
                                    :value="m.id"
                                >
                                    {{ m.manifest_code }} —
                                    {{ m.route_name }} (Xe:
                                    {{ m.vehicle_number || 'Chưa gán' }})
                                </option>
                            </select>
                        </div>

                        <div>
                            <label
                                class="mb-1 block font-semibold text-muted-foreground"
                                >Nhân viên giao hàng / Shipper / Tài xế *</label
                            >
                            <select
                                v-model="dispatchForm.transporter_id"
                                class="w-full rounded-lg border border-input bg-background px-3 py-2 text-foreground"
                            >
                                <option value="" disabled>
                                    -- Chọn nhân viên giao hàng --
                                </option>
                                <option
                                    v-for="staff in warehouseStaff"
                                    :key="staff.id"
                                    :value="staff.id"
                                >
                                    {{ staff.name }} · {{ staff.job_title }}
                                    {{
                                        isCurrentAssignee(staff.id)
                                            ? '(Đang phân công)'
                                            : ''
                                    }}
                                </option>
                            </select>
                            <p
                                v-if="!isTransporterAssignmentOnly"
                                class="mt-1 text-[11px] text-muted-foreground"
                            >
                                Sau khi xuất kho, tài khoản này sẽ nhận task
                                “Giao hàng thành công” trong mục Công việc.
                            </p>
                            <p
                                v-else
                                class="mt-1 text-[11px] text-muted-foreground"
                            >
                                Tài khoản này sẽ nhận công việc “Giao hàng thành
                                công” trong mục Công việc.
                            </p>
                        </div>

                        <div v-if="!isTransporterAssignmentOnly">
                            <label
                                class="mb-1 block font-semibold text-muted-foreground"
                                >Mã Niêm Phong Kiện Hàng (Seal Code)</label
                            >
                            <Input
                                v-model="dispatchForm.seal_code"
                                placeholder="Ví dụ: SEAL-889922, Niêm chì #05..."
                                class="h-9 text-xs"
                            />
                        </div>

                        <div>
                            <label
                                class="mb-1 block font-semibold text-muted-foreground"
                                >Ghi chú vận chuyển & đóng gói</label
                            >
                            <textarea
                                v-model="dispatchForm.notes"
                                rows="2"
                                placeholder="Ghi chú thêm về kiện hàng, phương tiện..."
                                class="w-full rounded-lg border border-input bg-background p-2.5 text-xs text-foreground"
                            ></textarea>
                        </div>
                    </div>

                    <div
                        class="flex items-center justify-end gap-3 border-t bg-muted/20 px-6 py-4"
                    >
                        <Button
                            @click="isDispatchModalOpen = false"
                            variant="outline"
                            size="sm"
                            >Hủy</Button
                        >
                        <Button
                            @click="submitDispatchModal"
                            :disabled="isProcessing"
                            size="sm"
                            class="bg-purple-600 font-bold text-white hover:bg-purple-700"
                        >
                            <Truck class="mr-1 h-4 w-4" />
                            {{ isTransporterAssignmentOnly ? 'Lưu nhân viên giao' : 'Xác Nhận Xuất Kho Bàn Giao' }}
                        </Button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Warehouse task assignment modal -->

        <div
            v-if="isTaskModalOpen"
            class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-950/70 p-4 backdrop-blur-sm overflow-y-auto"
        >
            <div
                class="my-auto flex max-h-[90vh] w-full max-w-lg flex-col overflow-hidden rounded-2xl border border-indigo-500/30 bg-card shadow-2xl"
            >
                <div
                    class="flex shrink-0 items-center justify-between border-b border-border bg-indigo-950/50 px-5 py-4"
                >
                    <div>
                        <h3
                            class="flex items-center gap-2 text-base font-bold text-foreground"
                        >
                            <ClipboardList class="h-5 w-5 text-indigo-300" />
                            Giao việc Kho Tổng
                        </h3>
                        <p class="mt-1 text-[11px] text-indigo-200/80">
                            Đơn
                            {{
                                getSupplyRequestById(
                                    Number(taskForm.supply_request_id),
                                )?.request_code ||
                                `#${taskForm.supply_request_id}`
                            }}
                        </p>
                    </div>
                    <button
                        @click="isTaskModalOpen = false"
                        class="rounded-lg p-1 text-muted-foreground hover:text-foreground"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>
                <div class="flex-1 space-y-4 overflow-y-auto p-5 text-xs">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label
                                class="mb-1 block font-semibold text-muted-foreground"
                                >Chặng việc</label
                            >
                            <select
                                v-model="taskForm.task_type"
                                class="w-full rounded-lg border border-input bg-background px-3 py-2 text-foreground"
                            >
                                <option value="picking">Soạn hàng FEFO</option>
                                <option value="handover">
                                    Bàn giao / xuất xe
                                </option>
                            </select>
                        </div>
                        <div>
                            <label
                                class="mb-1 block font-semibold text-muted-foreground"
                                >Nhân viên nhận việc</label
                            >
                            <select
                                v-model="taskForm.assigned_to"
                                class="w-full rounded-lg border border-input bg-background px-3 py-2 text-foreground"
                            >
                                <option value="" disabled>
                                    Chọn nhân viên
                                </option>
                                <option
                                    v-for="staff in warehouseStaff"
                                    :key="staff.id"
                                    :value="staff.id"
                                >
                                    {{ staff.name }} · {{ staff.job_title }}
                                    {{
                                        isCurrentAssignee(staff.id)
                                            ? '(Đang phân công)'
                                            : ''
                                    }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label
                                class="mb-1 block font-semibold text-muted-foreground"
                                >Ưu tiên</label
                            >
                            <select
                                v-model="taskForm.priority"
                                class="w-full rounded-lg border border-input bg-background px-3 py-2 text-foreground"
                            >
                                <option value="normal">Bình thường</option>
                                <option value="high">Cao</option>
                                <option value="urgent">Khẩn</option>
                            </select>
                        </div>
                        <div>
                            <label
                                class="mb-1 block font-semibold text-muted-foreground"
                                >Hạn hoàn tất</label
                            >
                            <Input
                                v-model="taskForm.due_at"
                                type="datetime-local"
                                class="h-9 text-xs"
                            />
                        </div>
                    </div>
                    <div>
                        <label
                            class="mb-1 block font-semibold text-muted-foreground"
                            >Ghi chú điều phối</label
                        >
                        <textarea
                            v-model="taskForm.notes"
                            rows="3"
                            placeholder="VD: Ưu tiên rau củ, cần hoàn tất trước 08:00..."
                            class="w-full rounded-lg border border-input bg-background px-3 py-2 text-xs text-foreground outline-none focus:border-indigo-500"
                        ></textarea>
                    </div>
                </div>
                <div
                    class="flex shrink-0 justify-end gap-2 border-t border-border bg-muted/20 px-5 py-4"
                >
                    <Button
                        @click="isTaskModalOpen = false"
                        variant="outline"
                        size="sm"
                        >Hủy</Button
                    >
                    <Button
                        @click="submitTaskAssignment"
                        size="sm"
                        :disabled="isTaskProcessing || !warehouseStaff.length"
                        class="gap-1.5 bg-indigo-600 text-white hover:bg-indigo-700"
                    >
                        <UserCheck class="h-4 w-4" /> Xác nhận giao việc
                    </Button>
                </div>
            </div>
        </div>

        <!-- Picking & FEFO Modal -->

        <div
            v-if="isPickingModalOpen && selectedRequest"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/70 p-4 backdrop-blur-sm"
        >
            <div
                class="flex max-h-[90vh] w-full max-w-4xl flex-col overflow-hidden rounded-2xl border border-border bg-card shadow-2xl"
            >
                <div
                    class="flex items-center justify-between border-b bg-amber-950 p-5 text-white"
                >
                    <div>
                        <h3 class="flex items-center gap-2 text-lg font-bold">
                            <Boxes class="h-5 w-5 text-amber-400" />
                            Phiếu Soạn Hàng & Quét Mã Lô (FEFO) —
                            {{ selectedRequest.request_code }}
                        </h3>
                        <p class="text-xs text-amber-200">
                            Nhập số lượng thực xuất, mã lô hàng và vị trí lưu
                            trữ. Hệ thống yêu cầu nhập lý do nếu chọn lô khác
                            FEFO.
                        </p>
                    </div>
                    <button
                        @click="isPickingModalOpen = false"
                        class="rounded-lg p-1 text-amber-200 hover:text-white"
                    >
                        <X class="h-6 w-6" />
                    </button>
                </div>

                <div class="flex-1 space-y-4 overflow-y-auto p-6 text-xs">
                    <div class="overflow-x-auto rounded-xl border">
                        <table class="w-full text-left">
                            <thead
                                class="border-b bg-muted/50 font-semibold text-muted-foreground"
                            >
                                <tr>
                                    <th class="p-3">Nguyên Liệu</th>
                                    <th class="p-3 text-right">Duyệt</th>
                                    <th class="p-3 text-right">Thực Soạn</th>
                                    <th class="p-3">Mã Lô (Batch ID)</th>
                                    <th class="p-3">Vị Trí Kho</th>
                                    <th class="p-3">Lý Do Khác FEFO</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr
                                    v-for="item in pickingItems"
                                    :key="item.id"
                                    class="hover:bg-muted/20"
                                >
                                    <td
                                        class="p-3 font-semibold text-foreground"
                                    >
                                        {{ item.ingredient_name }}
                                    </td>
                                    <td
                                        class="p-3 text-right font-bold text-muted-foreground"
                                    >
                                        {{ item.approved_quantity }}
                                        {{ item.unit_symbol }}
                                    </td>
                                    <td class="p-3 text-right">
                                        <input
                                            type="number"
                                            step="0.1"
                                            min="0"
                                            v-model.number="
                                                item.actual_dispatched_quantity
                                            "
                                            class="w-24 rounded border border-input bg-background px-2 py-1 text-right font-bold text-indigo-400 focus:outline-none"
                                        />
                                    </td>
                                    <td class="p-3">
                                        <input
                                            type="number"
                                            placeholder="Batch ID"
                                            v-model.number="item.batch_id"
                                            class="w-28 rounded border border-input bg-background px-2 py-1 font-mono text-xs text-foreground focus:outline-none"
                                        />
                                    </td>
                                    <td class="p-3">
                                        <input
                                            type="number"
                                            placeholder="Location ID"
                                            v-model.number="
                                                item.warehouse_location_id
                                            "
                                            class="w-24 rounded border border-input bg-background px-2 py-1 text-xs text-foreground focus:outline-none"
                                        />
                                    </td>
                                    <td class="p-3">
                                        <input
                                            type="text"
                                            placeholder="Lý do nếu không chọn FEFO..."
                                            v-model="item.non_fefo_reason"
                                            class="w-full rounded border border-input bg-background px-2 py-1 text-xs text-foreground focus:outline-none"
                                        />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div
                    class="flex items-center justify-end gap-3 border-t bg-muted/20 p-4"
                >
                    <Button
                        @click="isPickingModalOpen = false"
                        variant="outline"
                        size="sm"
                        >Hủy</Button
                    >
                    <Button
                        @click="submitPreparePicking"
                        size="sm"
                        :disabled="isProcessing"
                        class="bg-amber-600 text-white hover:bg-amber-700"
                    >
                        <Check class="mr-1 h-4 w-4" /> Xác Nhận Soạn Hàng
                    </Button>
                </div>
            </div>
        </div>

        <!-- ── MODAL 1: SMART ALLOCATION (FAIR-SHARE) ── -->

        <div
            v-if="isSmartModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
        >
            <div
                class="flex max-h-[85vh] w-full max-w-3xl flex-col overflow-hidden rounded-xl border border-border bg-card shadow-2xl"
            >
                <div
                    class="flex items-center justify-between border-b bg-indigo-950/40 px-6 py-4"
                >
                    <div class="flex items-center gap-2">
                        <Activity class="h-5 w-5 text-indigo-400" />
                        <h3 class="text-base font-bold text-foreground">
                            Gợi Ý Phân Bổ Fair-Share Khi Kho Tổng Thiếu Hàng
                        </h3>
                    </div>
                    <button
                        @click="isSmartModalOpen = false"
                        class="text-muted-foreground hover:text-foreground"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>
                <div class="space-y-4 overflow-y-auto p-6">
                    <p class="text-xs text-muted-foreground">
                        Thuật toán tự động tính toán dựa trên tổng tồn khả dụng
                        Kho Tổng và tổng nhu cầu của các chi nhánh để gợi ý mức
                        chia công bằng.
                    </p>
                    <div
                        class="overflow-x-auto rounded-lg border border-border"
                    >
                        <table class="w-full text-left text-xs">
                            <thead
                                class="bg-muted/50 font-semibold text-muted-foreground"
                            >
                                <tr>
                                    <th class="p-3">Chi Nhánh</th>
                                    <th class="p-3">Nguyên Liệu</th>
                                    <th class="p-3 text-right">Yêu Cầu</th>
                                    <th class="p-3 text-right">
                                        Tồn Kho Khả Dụng
                                    </th>
                                    <th
                                        class="p-3 text-right font-bold text-indigo-400"
                                    >
                                        Gợi Ý Phân Bổ
                                    </th>
                                    <th class="p-3 text-right text-rose-400">
                                        Thiếu Hụt
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr
                                    v-for="(s, idx) in smartSuggestions"
                                    :key="idx"
                                    class="hover:bg-muted/20"
                                >
                                    <td
                                        class="p-3 font-semibold text-foreground"
                                    >
                                        {{ s.branch_name }}
                                    </td>
                                    <td class="p-3 text-muted-foreground">
                                        {{ s.ingredient_name }}
                                    </td>
                                    <td class="p-3 text-right font-medium">
                                        {{ s.requested_qty }}
                                    </td>
                                    <td
                                        class="p-3 text-right font-medium text-amber-400"
                                    >
                                        {{ s.available_stock }}
                                    </td>
                                    <td
                                        class="bg-indigo-500/10 p-3 text-right font-bold text-indigo-400"
                                    >
                                        {{ s.suggested_qty }}
                                    </td>
                                    <td
                                        class="p-3 text-right font-semibold"
                                        :class="
                                            s.is_shortage
                                                ? 'text-rose-400'
                                                : 'text-emerald-400'
                                        "
                                    >
                                        {{ s.shortage_qty }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div
                    class="flex items-center justify-end gap-3 border-t bg-muted/20 px-6 py-4"
                >
                    <Button
                        @click="isSmartModalOpen = false"
                        variant="outline"
                        size="sm"
                        >Đóng</Button
                    >
                </div>
            </div>
        </div>

        <!-- ── MODAL 2: CENTRAL KITCHEN (SƠ CHẾ & SẢN XUẤT) ── -->

        <div
            v-if="isKitchenModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
        >
            <div
                class="flex max-h-[85vh] w-full max-w-4xl flex-col overflow-hidden rounded-xl border border-border bg-card shadow-2xl"
            >
                <div
                    class="flex items-center justify-between border-b bg-amber-950/40 px-6 py-4"
                >
                    <div class="flex items-center gap-2">
                        <Boxes class="h-5 w-5 text-amber-400" />
                        <h3 class="text-base font-bold text-foreground">
                            Central Kitchen - Sơ Chế & Sản Xuất Trung Tâm
                        </h3>
                    </div>
                    <button
                        @click="isKitchenModalOpen = false"
                        class="text-muted-foreground hover:text-foreground"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>
                <div class="space-y-6 overflow-y-auto p-6">
                    <!-- Tạo Đơn Sơ Chế Mới -->
                    <div
                        class="space-y-3 rounded-xl border border-amber-500/20 bg-amber-500/5 p-4"
                    >
                        <h4
                            class="text-xs font-bold tracking-wider text-amber-400 uppercase"
                        >
                            Tạo Lệnh Sơ Chế Mới (Work Order)
                        </h4>
                        <div
                            class="grid grid-cols-1 gap-3 text-xs sm:grid-cols-3"
                        >
                            <div>
                                <label class="mb-1 block text-muted-foreground"
                                    >Thành Phẩm Sơ Chế</label
                                >
                                <select
                                    v-model="newWo.output_ingredient_id"
                                    class="w-full rounded border border-input bg-background px-3 py-1.5 text-foreground"
                                >
                                    <option
                                        v-for="ing in ingredients"
                                        :key="ing.id"
                                        :value="ing.id"
                                    >
                                        {{ ing.name }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-muted-foreground"
                                    >Số Lượng Mục Tiêu</label
                                >
                                <Input
                                    v-model.number="newWo.target_quantity"
                                    type="number"
                                    min="0.1"
                                    step="0.1"
                                    class="h-8"
                                />
                            </div>
                            <div class="flex items-end">
                                <Button
                                    @click="createWorkOrder"
                                    size="sm"
                                    class="w-full bg-amber-600 text-white hover:bg-amber-700"
                                    >Tạo Lệnh Sơ Chế</Button
                                >
                            </div>
                        </div>
                    </div>

                    <!-- Danh sách Work Orders -->
                    <div>
                        <h4 class="mb-2 text-xs font-bold text-foreground">
                            Danh Sách Lệnh Sơ Chế Hiện Tại
                        </h4>
                        <div
                            class="overflow-x-auto rounded-lg border border-border"
                        >
                            <table class="w-full text-left text-xs">
                                <thead
                                    class="bg-muted/50 font-semibold text-muted-foreground"
                                >
                                    <tr>
                                        <th class="p-3">Mã Lệnh</th>
                                        <th class="p-3">Thành Phẩm</th>
                                        <th class="p-3 text-right">Mục Tiêu</th>
                                        <th class="p-3 text-right">Thực Thu</th>
                                        <th class="p-3">Trạng Thái</th>
                                        <th class="p-3 text-right">
                                            Hành Động
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border">
                                    <tr v-if="workOrders.length === 0">
                                        <td
                                            colspan="6"
                                            class="p-4 text-center text-muted-foreground"
                                        >
                                            Chưa có lệnh sơ chế nào.
                                        </td>
                                    </tr>
                                    <tr
                                        v-for="wo in workOrders"
                                        :key="wo.id"
                                        class="hover:bg-muted/20"
                                    >
                                        <td
                                            class="p-3 font-mono font-bold text-foreground"
                                        >
                                            {{ wo.work_order_code }}
                                        </td>
                                        <td class="p-3 font-medium">
                                            {{ wo.output_ingredient?.name }}
                                        </td>
                                        <td
                                            class="p-3 text-right font-bold text-muted-foreground"
                                        >
                                            {{ wo.target_quantity }}
                                        </td>
                                        <td
                                            class="p-3 text-right font-bold text-amber-400"
                                        >
                                            {{
                                                wo.actual_yield_quantity || '-'
                                            }}
                                        </td>
                                        <td class="p-3">
                                            <span
                                                class="rounded px-2 py-0.5 text-[10px] font-bold"
                                                :class="
                                                    wo.status === 'completed'
                                                        ? 'bg-emerald-500/20 text-emerald-400'
                                                        : 'bg-amber-500/20 text-amber-400'
                                                "
                                            >
                                                {{ wo.status }}
                                            </span>
                                        </td>
                                        <td class="p-3 text-right">
                                            <Button
                                                v-if="wo.status !== 'completed'"
                                                @click="executeWo(wo)"
                                                size="sm"
                                                class="bg-amber-600 text-white"
                                                >Hoàn Tất Sơ Chế</Button
                                            >
                                            <span
                                                v-else
                                                class="font-bold text-emerald-400"
                                                >Lô:
                                                {{
                                                    wo.created_batch_code
                                                }}</span
                                            >
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div
                    class="flex items-center justify-end gap-3 border-t bg-muted/20 px-6 py-4"
                >
                    <Button
                        @click="isKitchenModalOpen = false"
                        variant="outline"
                        size="sm"
                        >Đóng</Button
                    >
                </div>
            </div>
        </div>

        <!-- ── MODAL 3: DELIVERY MANIFESTS (GOM CHUYẾN XE) ── -->

        <div
            v-if="isManifestModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
        >
            <div
                class="flex max-h-[85vh] w-full max-w-4xl flex-col overflow-hidden rounded-xl border border-border bg-card shadow-2xl"
            >
                <div
                    class="flex items-center justify-between border-b bg-purple-950/40 px-6 py-4"
                >
                    <div class="flex items-center gap-2">
                        <Truck class="h-5 w-5 text-purple-400" />
                        <h3 class="text-base font-bold text-foreground">
                            Gom Chuyến Xe Logistics & Master Packing List
                        </h3>
                    </div>
                    <button
                        @click="isManifestModalOpen = false"
                        class="text-muted-foreground hover:text-foreground"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>
                <div class="space-y-6 overflow-y-auto p-6">
                    <div class="flex items-center justify-between">
                        <p class="text-xs text-muted-foreground">
                            Gom tất cả đơn đã duyệt thành chuyến xe xuất bến
                            cùng mã niêm phong.
                        </p>
                        <Button
                            @click="createManifest"
                            size="sm"
                            class="bg-purple-600 text-white hover:bg-purple-700"
                            >Tạo Chuyến Xe Gom Đơn</Button
                        >
                    </div>

                    <div
                        class="overflow-x-auto rounded-lg border border-border"
                    >
                        <table class="w-full text-left text-xs">
                            <thead
                                class="bg-muted/50 font-semibold text-muted-foreground"
                            >
                                <tr>
                                    <th class="p-3">Mã Chuyến Xe</th>
                                    <th class="p-3">Tuyến Đường</th>
                                    <th class="p-3">Tài Xế / Xe</th>
                                    <th class="p-3">Mã Niêm Phong</th>
                                    <th class="p-3">Trạng Thái</th>
                                    <th class="p-3 text-right">Hành Động</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr v-if="manifests.length === 0">
                                    <td
                                        colspan="6"
                                        class="p-4 text-center text-muted-foreground"
                                    >
                                        Chưa có chuyến xe nào.
                                    </td>
                                </tr>
                                <tr
                                    v-for="m in manifests"
                                    :key="m.id"
                                    class="hover:bg-muted/20"
                                >
                                    <td
                                        class="p-3 font-mono font-bold text-foreground"
                                    >
                                        {{ m.manifest_code }}
                                    </td>
                                    <td class="p-3 font-medium">
                                        {{ m.route_name }}
                                    </td>
                                    <td class="p-3 text-muted-foreground">
                                        {{ m.driver_name }} ({{
                                            m.vehicle_number
                                        }})
                                    </td>
                                    <td class="p-3 font-mono text-purple-400">
                                        {{ m.seal_code || 'Chưa gán' }}
                                    </td>
                                    <td class="p-3">
                                        <span
                                            class="rounded px-2 py-0.5 text-[10px] font-bold"
                                            :class="
                                                m.status === 'dispatched'
                                                    ? 'bg-purple-500/20 text-purple-400'
                                                    : 'bg-slate-500/20 text-slate-400'
                                            "
                                        >
                                            {{ m.status }}
                                        </span>
                                    </td>
                                    <td class="p-3 text-right">
                                        <Button
                                            v-if="m.status !== 'dispatched'"
                                            @click="dispatchManifest(m)"
                                            size="sm"
                                            class="bg-purple-600 text-white"
                                            >Xuất Bến Chuyến Xe</Button
                                        >
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div
                    class="flex items-center justify-end gap-3 border-t bg-muted/20 px-6 py-4"
                >
                    <Button
                        @click="isManifestModalOpen = false"
                        variant="outline"
                        size="sm"
                        >Đóng</Button
                    >
                </div>
            </div>
        </div>

        <!-- ── MODAL 4: BATCH RECALL (THU HỒI LÔ KHẨN CẤP) ── -->

        <div
            v-if="isRecallModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
        >
            <div
                class="flex max-h-[85vh] w-full max-w-2xl flex-col overflow-hidden rounded-xl border border-rose-500/30 bg-card shadow-2xl"
            >
                <div
                    class="flex items-center justify-between border-b bg-rose-950/50 px-6 py-4"
                >
                    <div class="flex items-center gap-2">
                        <AlertTriangle class="h-5 w-5 text-rose-400" />
                        <h3 class="text-base font-bold text-rose-300">
                            Phát Lệnh Thu Hồi Lô Khẩn Cấp Toàn Chuỗi
                        </h3>
                    </div>
                    <button
                        @click="isRecallModalOpen = false"
                        class="text-muted-foreground hover:text-foreground"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>
                <div class="space-y-6 overflow-y-auto p-6">
                    <div
                        class="space-y-3 rounded-xl border border-rose-500/30 bg-rose-500/10 p-4"
                    >
                        <h4
                            class="text-xs font-bold tracking-wider text-rose-400 uppercase"
                        >
                            Kích Hoạt Thu Hồi 1-Click
                        </h4>
                        <div class="space-y-3 text-xs">
                            <div>
                                <label class="mb-1 block text-muted-foreground"
                                    >ID Lô Hàng Cần Thu Hồi (Batch ID)</label
                                >
                                <Input
                                    v-model.number="recallBatchId"
                                    type="number"
                                    placeholder="Nhập Batch ID..."
                                    class="h-8"
                                />
                            </div>
                            <div>
                                <label class="mb-1 block text-muted-foreground"
                                    >Lý Do Thu Hồi Khẩn Cấp</label
                                >
                                <Input
                                    v-model="recallReason"
                                    type="text"
                                    placeholder="VD: Lỗi kiểm nghiệm vi sinh / Hàng hết hạn sớm từ NCC..."
                                    class="h-8"
                                />
                            </div>
                            <Button
                                @click="submitRecall"
                                size="sm"
                                class="w-full bg-rose-600 font-bold text-white hover:bg-rose-700"
                                >LẬP TỨC KHÓA & THU HỒI LÔ TOÀN CHUỖI</Button
                            >
                        </div>
                    </div>

                    <div>
                        <h4 class="mb-2 text-xs font-bold text-foreground">
                            Lịch Sử Lệnh Thu Hồi
                        </h4>
                        <div
                            class="overflow-x-auto rounded-lg border border-border"
                        >
                            <table class="w-full text-left text-xs">
                                <thead
                                    class="bg-muted/50 font-semibold text-muted-foreground"
                                >
                                    <tr>
                                        <th class="p-3">Mã Thu Hồi</th>
                                        <th class="p-3">Tên Nguyên Liệu</th>
                                        <th class="p-3">Mức Độ</th>
                                        <th class="p-3">Lý Do</th>
                                        <th class="p-3 text-right">
                                            Chi Nhánh Ảnh Hưởng
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border">
                                    <tr v-if="recallOrders.length === 0">
                                        <td
                                            colspan="5"
                                            class="p-4 text-center text-muted-foreground"
                                        >
                                            Chưa có lệnh thu hồi nào.
                                        </td>
                                    </tr>
                                    <tr
                                        v-for="r in recallOrders"
                                        :key="r.id"
                                        class="hover:bg-muted/20"
                                    >
                                        <td
                                            class="p-3 font-mono font-bold text-rose-400"
                                        >
                                            {{ r.recall_code }}
                                        </td>
                                        <td class="p-3 font-medium">
                                            {{ r.batch?.ingredient?.name }}
                                        </td>
                                        <td
                                            class="p-3 font-bold text-rose-500 uppercase"
                                        >
                                            {{ r.severity }}
                                        </td>
                                        <td
                                            class="max-w-xs truncate p-3 text-muted-foreground"
                                        >
                                            {{ r.reason }}
                                        </td>
                                        <td
                                            class="p-3 text-right font-bold text-amber-400"
                                        >
                                            {{ r.affected_branches_count }} CN
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div
                    class="flex items-center justify-end gap-3 border-t bg-muted/20 px-6 py-4"
                >
                    <Button
                        @click="isRecallModalOpen = false"
                        variant="outline"
                        size="sm"
                        >Đóng</Button
                    >
                </div>
            </div>
        </div>

        <!-- Warehouse locations modal -->
        <Teleport to="body">
            <div
                v-if="isLocationModalOpen"
                class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-950/70 p-4 backdrop-blur-sm"
            >
                <div
                    class="flex max-h-[88vh] w-full max-w-3xl flex-col overflow-hidden rounded-2xl border border-sky-500/30 bg-card shadow-2xl"
                >
                    <div
                        class="flex items-center justify-between border-b border-border bg-sky-950/40 px-5 py-4"
                    >
                        <div>
                            <h3
                                class="flex items-center gap-2 text-base font-bold text-foreground"
                            >
                                <MapPin class="h-5 w-5 text-sky-300" /> Vị trí
                                Kho Tổng
                            </h3>
                            <p class="mt-1 text-[11px] text-sky-200/80">
                                Quản lý zone, rack, kệ, bin và khu vực lạnh/cách
                                ly.
                            </p>
                        </div>
                        <button
                            type="button"
                            @click="isLocationModalOpen = false"
                            class="rounded-lg p-1 text-muted-foreground hover:text-foreground"
                        >
                            <X class="h-5 w-5" />
                        </button>
                    </div>

                    <div
                        class="grid flex-1 gap-5 overflow-y-auto p-5 lg:grid-cols-[1fr_1.2fr]"
                    >
                        <div
                            v-if="canManageWarehouse"
                            class="space-y-3 rounded-xl border border-border bg-muted/20 p-4"
                        >
                            <h4
                                class="text-xs font-bold tracking-wider text-sky-300 uppercase"
                            >
                                Tạo vị trí mới
                            </h4>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label
                                        class="mb-1 block text-[11px] font-semibold text-muted-foreground"
                                        >Khu vực / Zone</label
                                    >
                                    <Input
                                        v-model="locationForm.zone"
                                        placeholder="VD: KHO KHÔ"
                                        class="h-8 text-xs"
                                    />
                                </div>
                                <div>
                                    <label
                                        class="mb-1 block text-[11px] font-semibold text-muted-foreground"
                                        >Mã vị trí</label
                                    >
                                    <Input
                                        v-model="locationForm.location_code"
                                        placeholder="VD: KHO-KHO-A01"
                                        class="h-8 text-xs"
                                    />
                                </div>
                                <div>
                                    <label
                                        class="mb-1 block text-[11px] font-semibold text-muted-foreground"
                                        >Rack</label
                                    >
                                    <Input
                                        v-model="locationForm.rack"
                                        placeholder="A"
                                        class="h-8 text-xs"
                                    />
                                </div>
                                <div>
                                    <label
                                        class="mb-1 block text-[11px] font-semibold text-muted-foreground"
                                        >Kệ / Shelf</label
                                    >
                                    <Input
                                        v-model="locationForm.shelf"
                                        placeholder="01"
                                        class="h-8 text-xs"
                                    />
                                </div>
                                <div>
                                    <label
                                        class="mb-1 block text-[11px] font-semibold text-muted-foreground"
                                        >Bin</label
                                    >
                                    <Input
                                        v-model="locationForm.bin"
                                        placeholder="01"
                                        class="h-8 text-xs"
                                    />
                                </div>
                            </div>
                            <div
                                class="space-y-2 text-xs text-muted-foreground"
                            >
                                <label class="flex items-center gap-2"
                                    ><input
                                        v-model="locationForm.is_cold_storage"
                                        type="checkbox"
                                        class="rounded border-input"
                                    />
                                    Kho lạnh</label
                                >
                                <label class="flex items-center gap-2"
                                    ><input
                                        v-model="locationForm.is_quarantine"
                                        type="checkbox"
                                        class="rounded border-input"
                                    />
                                    Khu cách ly</label
                                >
                            </div>
                            <Button
                                @click="saveLocation"
                                :disabled="isSavingLocation"
                                size="sm"
                                class="w-full bg-sky-600 text-white hover:bg-sky-700"
                            >
                                {{
                                    isSavingLocation
                                        ? 'Đang lưu...'
                                        : 'Lưu vị trí kho'
                                }}
                            </Button>
                        </div>

                        <div class="min-w-0">
                            <div class="mb-2 flex items-center justify-between">
                                <h4
                                    class="text-xs font-bold tracking-wider text-foreground uppercase"
                                >
                                    Danh sách vị trí
                                </h4>
                                <span class="text-[11px] text-muted-foreground"
                                    >{{ locations.length }} vị trí</span
                                >
                            </div>
                            <div
                                class="max-h-[45vh] overflow-auto rounded-xl border border-border"
                            >
                                <table class="w-full text-left text-xs">
                                    <thead
                                        class="sticky top-0 border-b border-border bg-muted/80 text-muted-foreground"
                                    >
                                        <tr>
                                            <th class="p-3">Mã</th>
                                            <th class="p-3">Zone</th>
                                            <th class="p-3">Cấu trúc</th>
                                            <th class="p-3 text-right">Loại</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-border">
                                        <tr v-if="locations.length === 0">
                                            <td
                                                colspan="4"
                                                class="p-5 text-center text-muted-foreground"
                                            >
                                                Chưa có vị trí kho.
                                            </td>
                                        </tr>
                                        <tr
                                            v-for="location in locations"
                                            :key="location.id"
                                            class="hover:bg-muted/20"
                                        >
                                            <td
                                                class="p-3 font-mono font-bold text-sky-300"
                                            >
                                                {{ location.location_code }}
                                            </td>
                                            <td class="p-3 text-foreground">
                                                {{ location.zone }}
                                            </td>
                                            <td
                                                class="p-3 text-muted-foreground"
                                            >
                                                {{
                                                    [
                                                        location.rack,
                                                        location.shelf,
                                                        location.bin,
                                                    ]
                                                        .filter(Boolean)
                                                        .join(' / ') || '-'
                                                }}
                                            </td>
                                            <td
                                                class="p-3 text-right text-[10px] font-semibold uppercase"
                                            >
                                                <span
                                                    v-if="
                                                        location.is_quarantine
                                                    "
                                                    class="text-rose-400"
                                                    >Cách ly</span
                                                >
                                                <span
                                                    v-else-if="
                                                        location.is_cold_storage
                                                    "
                                                    class="text-cyan-300"
                                                    >Kho lạnh</span
                                                >
                                                <span
                                                    v-else
                                                    class="text-emerald-400"
                                                    >Thường</span
                                                >
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div
                        class="flex justify-end border-t border-border bg-muted/20 px-5 py-4"
                    >
                        <Button
                            @click="isLocationModalOpen = false"
                            variant="outline"
                            size="sm"
                            >Đóng</Button
                        >
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
