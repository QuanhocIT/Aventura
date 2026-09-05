<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import {
    AlertCircle,
    AlertTriangle,
    ArrowRight,
    BadgeCheck,
    Bell,
    Box,
    CheckCircle,
    CheckSquare,
    ChevronLeft,
    ChevronRight,
    ClipboardList,
    Clock,
    HardHat,
    History,
    Package,
    PackageCheck,
    PackageOpen,
    Plus,
    QrCode,
    RefreshCw,
    Scan,
    Shield,
    Trash2,
    Truck,
    Upload,
    Users,
    Warehouse,
    X,
    Zap,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
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

const props = defineProps<{
    centralBranch: any;
    myTasks: Array<any>;
    taskSummary: any;
    myVouchers: Array<any>;
    assignedVerificationVouchers: Array<any>;
    myHandovers: Array<any>;
    myDisputes: Array<any>;
    myReceivingReports: Array<any>;
    warehouseReceivingReports: Array<any>;
    handoverRecipients: Array<any>;
    locations: Array<any>;
    ingredients: Array<any>;
    warehouseStaff: Array<{ id: number; name: string; job_title?: string | null }>;
    notifications: Array<any>;
    canManageWarehouse: boolean;
    currentUser: any;
}>();

// ── State ─────────────────────────────────────────────────────────────────────

type TabId =
    | 'today'
    | 'receiving'
    | 'putaway'
    | 'picking'
    | 'packing'
    | 'counting'
    | 'incident'
    | 'handover'
    | 'delivery';

const activeTab = ref<TabId>('today');
const isLoading = ref(false);
const taskList = ref([...props.myTasks]);
const voucherList = ref([...props.myVouchers]);
const assignedVerificationList = ref([...props.assignedVerificationVouchers]);
const handoverList = ref([...props.myHandovers]);
const disputeList = ref([...props.myDisputes]);
const receivingReportList = ref([...props.myReceivingReports]);
const warehouseReceivingReportList = ref([...props.warehouseReceivingReports]);
const highlightedWarehouseReportId = ref<number | null>(null);
const notificationList = ref([...props.notifications]);
const historyList = ref<any[]>([]);
const taskSummaryData = ref({ ...props.taskSummary });
const scanInput = ref('');
const scanResult = ref<any>(null);
const isScanLoading = ref(false);
const showScanModal = ref(false);
const showNotifications = ref(false);
const showHistory = ref(false);
const isCameraScanning = ref(false);
const cameraError = ref('');
let refreshTimer: ReturnType<typeof setInterval> | null = null;
let cameraStream: MediaStream | null = null;

// GRN Form
const grnForm = ref({
    received_at: new Date().toISOString().slice(0, 16),
    external_receipt_reason: 'other' as
        | 'external_donation'
        | 'external_return'
        | 'other',
    external_source_name: '',
    external_reference: '',
    verification_assigned_to: null as number | null,
    quality_status: 'pending' as
        | 'pending'
        | 'passed'
        | 'conditional'
        | 'failed',
    quality_notes: '',
    notes: '',
    items: [] as Array<{
        ingredient_id: number | null;
        ingredient_name: string;
        unit_label: string;
        actual_qty: number;
        unit_cost: number;
        lot_number: string;
        manufactured_date: string;
        expiry_date: string;
        location_id: number | null;
    }>,
});
const grnFiles = ref<File[]>([]);
const isSubmittingGrn = ref(false);

const activeVerificationVoucher = ref<any | null>(null);
const verificationItems = ref<Array<{ voucher_item_id: number; actual_qty: number }>>([]);
const verificationNotes = ref('');
const verificationQualityStatus = ref<'passed' | 'conditional' | 'failed'>('passed');
const verificationQualityNotes = ref('');
const isSubmittingVerification = ref(false);

// Incident Form
const incidentForm = ref({
    incident_type: 'shortage' as
        | 'shortage'
        | 'damage'
        | 'expired'
        | 'wrong_item'
        | 'other',
    description: '',
    ingredient_id: null as number | null,
    batch_id: undefined as number | undefined,
    location_id: undefined as number | undefined,
    quantity_affected: undefined as number | undefined,
});
const incidentFiles = ref<File[]>([]);
const isSubmittingIncident = ref(false);

// Shift Handover Form
const handoverForm = ref({
    shift_date: new Date().toISOString().slice(0, 10),
    shift_type: 'day' as 'morning' | 'afternoon' | 'evening' | 'night',
    shift_label: '',
    notes: '',
    received_by: null as number | null,
});
const isSubmittingHandover = ref(false);

// Task action
const activeTaskId = ref<number | null>(null);
const taskResultNote = ref('');
const taskFiles = ref<File[]>([]);
const isProcessingTask = ref(false);

// FEFO Picking Modal
const showPickingModal = ref(false);
const activePickingTask = ref<any>(null);
const pickingFormItems = ref<
    Array<{
        id: number;
        ingredient_name: string;
        approved_quantity: number;
        actual_dispatched_quantity: number;
        batch_id: number | null;
        warehouse_location_id: number | null;
        unit_symbol?: string;
    }>
>([]);
const isSubmittingPicking = ref(false);

// ── Computed ──────────────────────────────────────────────────────────────────

const tabs = computed(() => [
    {
        id: 'today' as TabId,
        label: 'Việc Hôm Nay',
        icon: Zap,
        count:
            taskSummaryData.value.pending + taskSummaryData.value.in_progress,
    },
    {
        id: 'receiving' as TabId,
        label: 'Nhập ngoài vào Kho Tổng',
        icon: PackageOpen,
        count:
            assignedVerificationList.value.length +
            voucherList.value.filter(
                (v) => v.status === 'draft' || v.status === 'discrepancy',
            ).length,
    },
    {
        id: 'putaway' as TabId,
        label: 'Cất Hàng',
        icon: Box,
        count: tasksByType('putaway').length,
    },
    {
        id: 'picking' as TabId,
        label: 'Soạn Hàng FEFO',
        icon: ClipboardList,
        count: tasksByType('picking').length,
    },
    {
        id: 'packing' as TabId,
        label: 'Đóng Gói',
        icon: Package,
        count: tasksByType('packing').length,
    },
    {
        id: 'counting' as TabId,
        label: 'Kiểm Kê',
        icon: BadgeCheck,
        count: tasksByType('counting').length,
    },
    {
        id: 'incident' as TabId,
        label: 'Báo Sự Cố',
        icon: AlertTriangle,
        count: receivingReportList.value.filter(
            (report) => report.status === 'confirmed_pending_ack',
        ).length + warehouseReceivingReportList.value.length,
    },
    {
        id: 'delivery' as TabId,
        label: 'Giao tới Chi nhánh',
        icon: Truck,
        count: tasksByType('delivery').length,
    },
    { id: 'handover' as TabId, label: 'Bàn Giao Ca', icon: Truck, count: 0 },
]);

const tabsContainer = ref<HTMLElement | null>(null);
const canScrollLeft = ref(false);
const canScrollRight = ref(false);
const tabElementRefs = ref<Record<string, HTMLElement>>({});

function setTabRef(el: any, id: string) {
    if (el) {
        tabElementRefs.value[id] = el as HTMLElement;
    }
}

function checkTabScroll() {
    const el = tabsContainer.value;

    if (!el) {
        return;
    }

    canScrollLeft.value = el.scrollLeft > 5;
    canScrollRight.value = el.scrollLeft + el.clientWidth < el.scrollWidth - 5;
}

function scrollTabs(direction: 'left' | 'right') {
    const el = tabsContainer.value;

    if (!el) {
        return;
    }

    const amount = direction === 'left' ? -220 : 220;

    el.scrollBy({ left: amount, behavior: 'smooth' });
}

function handleTabsWheel(event: WheelEvent) {
    const el = tabsContainer.value;

    if (!el) {
        return;
    }

    if (event.deltaY !== 0 && el.scrollWidth > el.clientWidth) {
        el.scrollLeft += event.deltaY;
        checkTabScroll();
    }
}

function selectTab(tabId: TabId) {
    activeTab.value = tabId;

    const targetEl = tabElementRefs.value[tabId];

    if (targetEl && tabsContainer.value) {
        targetEl.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'nearest' });
    }

    setTimeout(checkTabScroll, 300);
}

const taskStatusFilter = ref<'all' | 'in_progress' | 'assigned' | 'overdue' | 'completed'>('all');

const overdueCount = computed(
    () =>
        taskList.value.filter(
            (t) =>
                t.is_overdue ||
                (t.due_at &&
                    new Date(t.due_at) < new Date() &&
                    !['completed', 'cancelled'].includes(t.status)),
        ).length,
);

const filteredTasks = computed(() => {
    if (taskStatusFilter.value === 'in_progress') {
        return taskList.value.filter((t) => t.status === 'in_progress');
    }

    if (taskStatusFilter.value === 'assigned') {
        return taskList.value.filter((t) => t.status === 'assigned');
    }

    if (taskStatusFilter.value === 'completed') {
        return taskList.value.filter((t) => t.status === 'completed');
    }

    if (taskStatusFilter.value === 'overdue') {
        return taskList.value.filter(
            (t) =>
                t.is_overdue ||
                (t.due_at &&
                    new Date(t.due_at) < new Date() &&
                    !['completed', 'cancelled'].includes(t.status)),
        );
    }

    return taskList.value;
});

const taskFilterCounts = computed(() => ({
    all: taskList.value.length,
    in_progress: taskList.value.filter((t) => t.status === 'in_progress').length,
    assigned: taskList.value.filter((t) => t.status === 'assigned').length,
    overdue: overdueCount.value,
    completed: taskList.value.filter((t) => t.status === 'completed').length,
}));

function tasksByType(type: string) {
    return taskList.value.filter(
        (t) => t.task_type === type && t.status !== 'completed',
    );
}

function priorityBadgeClass(priority: string) {
    switch (priority) {
        case 'urgent':
        case 'high':
            return 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border-rose-500/20';
        case 'normal':
            return 'bg-muted text-foreground border-border/80';
        default:
            return 'bg-muted/60 text-muted-foreground border-border/50';
    }
}

function priorityLabel(priority: string): string {
    switch (priority) {
        case 'urgent':
            return 'Khẩn cấp';
        case 'high':
            return 'Ưu tiên cao';
        case 'normal':
            return 'Bình thường';
        default:
            return 'Thấp';
    }
}

function taskTypeLabel(type: string): string {
    const map: Record<string, string> = {
        receiving: 'Nhập ngoài vào Kho Tổng',
        putaway: 'Cất hàng vào vị trí',
        picking: 'Soạn hàng theo đơn',
        packing: 'Đóng gói kiện hàng',
        handover: 'Bàn giao ca',
        delivery: 'Giao hàng tới chi nhánh',
        counting: 'Kiểm kê kho',
        incident: 'Xử lý sự cố',
        discrepancy_resolution: 'Xử lý sai lệch',
        shift_handover: 'Bàn giao ca',
    };

    return map[type] ?? type;
}

function formatBranchName(branch: any): string {
    if (!branch) {
        return '';
    }

    if (typeof branch === 'object') {
        return branch.name || branch.code || '';
    }

    return String(branch);
}

function formatLocationName(loc: any): string {
    if (!loc) {
        return '';
    }

    const code = loc.location_code || loc.code || `Vị trí #${loc.id}`;
    const zoneInfo = loc.zone ? `[${loc.zone}]` : '';
    const details = [
        loc.rack ? `Kệ ${loc.rack}` : '',
        loc.shelf ? `Ngăn ${loc.shelf}` : '',
        loc.bin ? `Hộp ${loc.bin}` : '',
    ]
        .filter(Boolean)
        .join(' - ');

    if (zoneInfo || details) {
        return `${code} ${zoneInfo} ${details ? '(' + details + ')' : ''}`.trim();
    }

    return loc.name || code;
}

function statusBadgeClass(status: string) {
    switch (status) {
        case 'assigned':
            return 'bg-sky-500/10 text-sky-700 dark:text-sky-300 border-sky-500/20';
        case 'in_progress':
            return 'bg-amber-500/10 text-amber-700 dark:text-amber-300 border-amber-500/20';
        case 'completed':
            return 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border-emerald-500/20';
        case 'cancelled':
            return 'bg-muted text-muted-foreground border-border';
        default:
            return 'bg-muted/60 text-muted-foreground border-border';
    }
}

function statusLabel(status: string): string {
    const map: Record<string, string> = {
        assigned: 'Chờ thực hiện',
        in_progress: 'Đang thực hiện',
        completed: 'Hoàn thành',
        cancelled: 'Đã hủy',
    };

    return map[status] ?? status;
}

function voucherStatusBadgeClass(s: string) {
    switch (s) {
        case 'draft':
            return 'bg-muted text-muted-foreground border-border';
        case 'confirmed':
            return 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border-emerald-500/20';
        case 'discrepancy':
            return 'bg-rose-500/10 text-rose-700 dark:text-rose-300 border-rose-500/20';
        case 'pending_review':
            return 'bg-amber-500/10 text-amber-700 dark:text-amber-300 border-amber-500/20';
        default:
            return 'bg-muted text-muted-foreground border-border';
    }
}

function voucherStatusLabel(s: string): string {
    const map: Record<string, string> = {
        draft: 'Bản nháp',
        confirmed: 'Đã nhập kho',
        discrepancy: 'Có chênh lệch (phiếu cũ)',
        pending_review: 'Chờ duyệt giải trình',
        closed: 'Đã hoàn tất',
    };

    return map[s] ?? s;
}

// ── API Actions ───────────────────────────────────────────────────────────────

async function refreshTasks(silent = false) {
    if (!silent) {
        isLoading.value = true;
    }

    try {
        const { data } = await axios.get('/api/warehouse/my-tasks');
        const oldTaskIds = new Set(taskList.value.map((t) => t.id));
        const newTasks = (data.tasks || []).filter(
            (t: any) => !oldTaskIds.has(t.id) && t.status !== 'completed',
        );

        if (silent && newTasks.length > 0) {
            toast.info(
                `Bạn có ${newTasks.length} nhiệm vụ mới vừa được phân công!`,
                {
                    duration: 4000,
                },
            );
        }

        taskList.value = data.tasks;
        taskSummaryData.value = data.summary;

        if (data.assigned_verification_vouchers) {
            const oldVoucherIds = new Set(
                assignedVerificationList.value.map((v) => v.id),
            );
            const newVouchers = (
                data.assigned_verification_vouchers || []
            ).filter((v: any) => !oldVoucherIds.has(v.id));

            if (silent && newVouchers.length > 0) {
                toast.info(
                    `Bạn có ${newVouchers.length} phiếu nhập ngoài mới (${newVouchers.map((v: any) => v.voucher_code).join(', ')}) chờ kiểm kê!`,
                    { duration: 5000 },
                );
                activeTab.value = 'receiving';
            }

            assignedVerificationList.value = data.assigned_verification_vouchers;
        }

        if (data.my_vouchers) {
            voucherList.value = data.my_vouchers;
        }

        if (data.my_handovers) {
            handoverList.value = data.my_handovers;
        }

        if (data.my_receiving_reports) {
            receivingReportList.value = data.my_receiving_reports;
        }

        if (data.warehouse_receiving_reports) {
            warehouseReceivingReportList.value = data.warehouse_receiving_reports;
        }

        if (!silent) {
            toast.success('Đã làm mới danh sách tác vụ.');
        }
    } catch {
        if (!silent) {
            toast.error('Không thể tải danh sách công việc.');
        }
    } finally {
        if (!silent) {
            isLoading.value = false;
        }
    }
}

async function startTask(taskOrId: any) {
    const task =
        typeof taskOrId === 'object'
            ? taskOrId
            : taskList.value.find((t) => t.id === taskOrId);
    const taskId = task?.id ?? taskOrId;

    if (!taskId) {
        return;
    }

    isProcessingTask.value = true;

    try {
        const { data } = await axios.post(
            `/api/warehouse/tasks/${taskId}/start`,
        );
        toast.success('Bắt đầu công việc thành công!');
        const idx = taskList.value.findIndex((t) => t.id === taskId);

        if (idx !== -1) {
            taskList.value[idx] = { ...taskList.value[idx], ...data.task };
        }

        taskSummaryData.value.in_progress++;
        taskSummaryData.value.pending = Math.max(
            0,
            taskSummaryData.value.pending - 1,
        );

        // Chỉ mở form thao tác trực tiếp nếu là task Soạn hàng FEFO ('picking')
        // Đối với Giao hàng ('delivery') hoặc task khác, bắt đầu chỉ chuyển trạng thái sang "Đang thực hiện".
        // Khi giao xong tới chi nhánh, tài xế/nhân viên mới ấn nút "Ấn giao hàng thành công" để xác nhận/hoàn thành.
        const currentTask =
            (idx !== -1 ? taskList.value[idx] : task) || { id: taskId };

        if (currentTask.task_type === 'picking') {
            openTaskCompletion(currentTask);
        }
    } catch (e: any) {
        if (task && task.task_type === 'picking') {
            openTaskCompletion(task);
        } else {
            toast.error(
                e.response?.data?.message ?? 'Lỗi khi bắt đầu công việc.',
            );
        }
    } finally {
        isProcessingTask.value = false;
    }
}

function getTaskActionButtonLabel(task: any) {
    switch (task.task_type) {
        case 'picking':
            return 'Kiểm & Soạn Hàng FEFO';
        case 'handover':
            return 'Bàn Giao Xuất Xe';
        case 'delivery':
            return 'Giao hàng thành công';
        case 'putaway':
            return 'Cất Hàng Vào Vị Trí';
        case 'packing':
            return 'Đóng Gói Kiện Hàng';
        case 'counting':
            return 'Kiểm Kê Kho';
        default:
            return 'Tiến Hành Tác Vụ';
    }
}

async function completeTask(taskId: number) {
    if (isProcessingTask.value) {
        return;
    }

    isProcessingTask.value = true;
    const formData = new FormData();
    const idempotencyKey =
        typeof crypto !== 'undefined' && crypto.randomUUID
            ? crypto.randomUUID()
            : `task-${taskId}-${Date.now()}-${Math.random().toString(16).slice(2)}`;
    formData.append('idempotency_key', idempotencyKey);

    if (taskResultNote.value) {
        formData.append('result_notes', taskResultNote.value);
    }

    taskFiles.value.forEach((f) => formData.append('evidence[]', f));

    try {
        await axios.post(`/api/warehouse/tasks/${taskId}/complete`, formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        toast.success('Hoàn thành công việc thành công!');
        // Đóng modal ngay lập tức và reset form
        activeTaskId.value = null;
        taskResultNote.value = '';
        taskFiles.value = [];
        await refreshTasks(true);
    } catch (e: any) {
        toast.error(
            e.response?.data?.message ?? 'Lỗi khi hoàn thành công việc.',
        );
    } finally {
        isProcessingTask.value = false;
    }
}

// GRN
const formatQuantity = (value: number | string | null | undefined) =>
    new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 3 }).format(
        Number(value || 0),
    );

const formatCurrency = (value: number | string | null | undefined) =>
    new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
        maximumFractionDigits: 0,
    }).format(Number(value || 0));

const ingredientUnit = (ingredientId: number | null) =>
    props.ingredients.find((ingredient: any) => ingredient.id === ingredientId)?.unit
        ?.symbol ?? 'đv';

const grnLineTotal = (item: { actual_qty: number; unit_cost: number }) =>
    Math.max(0, Number(item.actual_qty || 0) * Number(item.unit_cost || 0));

const totalReceiptValue = computed(() =>
    grnForm.value.items.reduce(
        (total, item) => total + grnLineTotal(item),
        0,
    ),
);

const voucherTotal = (voucher: any) => {
    const recordedTotal = Number(voucher.invoice_total_amount ?? 0);

    return recordedTotal > 0
        ? recordedTotal
        : (voucher.items ?? []).reduce(
              (total: number, item: any) => total + grnLineTotal(item),
              0,
          );
};

function openVerification(voucher: any) {
    activeVerificationVoucher.value = voucher;
    verificationItems.value = (voucher.items ?? []).map((item: any) => ({
        voucher_item_id: item.id,
        actual_qty: Number(item.expected_qty ?? item.actual_qty ?? 0),
    }));
    verificationNotes.value = '';
    verificationQualityStatus.value = 'passed';
    verificationQualityNotes.value = '';
}

function closeVerification() {
    activeVerificationVoucher.value = null;
    verificationItems.value = [];
    verificationNotes.value = '';
    verificationQualityStatus.value = 'passed';
    verificationQualityNotes.value = '';
}

function verificationHasIssue(): boolean {
    const voucher = activeVerificationVoucher.value;

    if (!voucher) {
        return false;
    }

    const hasDiscrepancy = verificationItems.value.some((item) => {
        const sourceItem = (voucher.items ?? []).find((row: any) => row.id === item.voucher_item_id);

        return Math.abs(Number(item.actual_qty) - Number(sourceItem?.expected_qty ?? 0)) > 0.0005;
    });

    return hasDiscrepancy || verificationQualityStatus.value !== 'passed';
}

function showWarehouseReceivingReport(report: any): void {
    warehouseReceivingReportList.value = [
        report,
        ...warehouseReceivingReportList.value.filter((item) => item.id !== report.id),
    ];
    highlightedWarehouseReportId.value = Number(report.id);
    activeTab.value = 'incident';

    const url = new URL(window.location.href);
    url.searchParams.set('tab', 'incident');
    url.searchParams.set('report_id', String(report.id));
    window.history.replaceState(window.history.state, '', `${url.pathname}${url.search}${url.hash}`);
}

async function submitVerification() {
    const voucher = activeVerificationVoucher.value;

    if (!voucher || isSubmittingVerification.value) {
        return;
    }

    if (verificationItems.value.some((item) => Number(item.actual_qty) <= 0)) {
        toast.error('Mỗi dòng phải có số lượng kiểm kê thực tế lớn hơn 0.');

        return;
    }

    const hasDiscrepancy = verificationItems.value.some((item) => {
        const sourceItem = (voucher.items ?? []).find((row: any) => row.id === item.voucher_item_id);

        return Math.abs(Number(item.actual_qty) - Number(sourceItem?.expected_qty ?? 0)) > 0.0005;
    });

    if (hasDiscrepancy && !verificationNotes.value.trim()) {
        toast.error('Số lượng lệch với khai báo của Trưởng kho; cần ghi chú giải trình.');

        return;
    }

    if (verificationQualityStatus.value === 'conditional' && !verificationQualityNotes.value.trim()) {
        toast.error('Hàng đạt có điều kiện phải có ghi chú xử lý chất lượng.');

        return;
    }

    isSubmittingVerification.value = true;

    try {
        const { data } = await axios.post(
            `/api/warehouse/receiving-vouchers/${voucher.id}/confirm`,
            {
                notes: verificationNotes.value.trim(),
                quality_status: verificationQualityStatus.value,
                quality_notes: verificationQualityNotes.value.trim(),
                verification_items: verificationItems.value,
            },
        );

        if (data.report) {
            showWarehouseReceivingReport(data.report);
        }

        assignedVerificationList.value = assignedVerificationList.value.filter(
            (item) => item.id !== voucher.id,
        );
        voucherList.value.unshift({
            ...voucher,
            status: 'confirmed',
            verified_by: { id: props.currentUser.id, name: props.currentUser.name },
            verified_at: new Date().toISOString(),
            total_actual_qty: verificationItems.value.reduce((sum, item) => sum + Number(item.actual_qty), 0),
        });
        closeVerification();

        toast.success(data.message || 'Đã kiểm kê và xác nhận nhập nguyên liệu thành công.');
    } catch (e: any) {
        const report = e.response?.data?.report;

        if (report) {
            showWarehouseReceivingReport(report);
            assignedVerificationList.value = assignedVerificationList.value.filter(
                (item) => item.id !== voucher.id,
            );
            closeVerification();
            toast.warning(
                e.response?.data?.message || 'Đã lập biên bản kiểm kê; phiếu đang chờ xử lý chất lượng.',
            );

            return;
        }

        toast.error(e.response?.data?.message ?? 'Không thể xác nhận phiếu nhập nguyên liệu.');
    } finally {
        isSubmittingVerification.value = false;
    }
}

function addGrnItem() {
    const newItem = {
        ingredient_id: null,
        ingredient_name: '',
        unit_label: '',
        actual_qty: 1,
        unit_cost: 0,
        lot_number: '',
        manufactured_date: '',
        expiry_date: '',
        location_id: null,
    };

    grnForm.value.items = [...grnForm.value.items, newItem];
}

function removeGrnItem(index: number) {
    grnForm.value.items.splice(index, 1);
}

function onGrnItemIngredientChange(item: { ingredient_id: number | null; unit_label: string }) {
    if (!item.unit_label.trim()) {
        item.unit_label = ingredientUnit(item.ingredient_id);
    }
}

async function submitGrn() {
    if (grnForm.value.items.length === 0) {
        toast.error('Vui lòng thêm ít nhất 1 nguyên liệu.');

        return;
    }

    if (!grnForm.value.external_source_name.trim()) {
        toast.error('Vui lòng ghi rõ bên giao hoặc nguồn bên ngoài. Đây không phải là trường nhà cung cấp.');

        return;
    }

    if (props.canManageWarehouse && !grnForm.value.verification_assigned_to) {
        toast.error('Trưởng kho phải phân công một nhân viên Kho Tổng kiểm kê trước khi lập phiếu.');

        return;
    }

    const invalidIndex = grnForm.value.items.findIndex(
        (item) =>
            !item.ingredient_id ||
            Number(item.actual_qty) <= 0 ||
            !item.unit_label.trim() ||
            !item.lot_number.trim(),
    );

    if (invalidIndex !== -1) {
        toast.error(
            `Dòng #${invalidIndex + 1}: cần chọn nguyên liệu, đơn vị tính, số lượng lớn hơn 0 và số lô.`,
        );

        return;
    }

    const invalidDateIndex = grnForm.value.items.findIndex(
        (item) =>
            item.manufactured_date &&
            item.expiry_date &&
            item.expiry_date < item.manufactured_date,
    );

    if (invalidDateIndex !== -1) {
        toast.error(
            `Dòng #${invalidDateIndex + 1}: Hạn sử dụng (HSD) không được nhỏ hơn Ngày sản xuất (NSX).`,
        );

        return;
    }

    isSubmittingGrn.value = true;
    const formData = new FormData();
    formData.append('received_at', grnForm.value.received_at);
    formData.append('external_receipt_reason', grnForm.value.external_receipt_reason);
    formData.append('external_source_name', grnForm.value.external_source_name.trim());
    formData.append('submit_for_review', 'true');

    if (grnForm.value.verification_assigned_to) {
        formData.append('verification_assigned_to', String(grnForm.value.verification_assigned_to));
    }

    formData.append('invoice_total_amount', String(totalReceiptValue.value));

    if (grnForm.value.external_reference.trim()) {
        formData.append('external_reference', grnForm.value.external_reference.trim());
    }

    const idempotencyKey =
        typeof crypto !== 'undefined' && crypto.randomUUID
            ? crypto.randomUUID()
            : `grn-${Date.now()}-${Math.random().toString(16).slice(2)}`;
    formData.append('idempotency_key', idempotencyKey);
    const grnMeta = {
        quality_status: grnForm.value.quality_status,
        quality_notes: grnForm.value.quality_notes,
    };
    Object.entries(grnMeta).forEach(([key, value]) => {
        if (value !== '' && value !== null && value !== undefined) {
            formData.append(key, String(value));
        }
    });

    if (grnForm.value.notes) {
        formData.append('notes', grnForm.value.notes);
    }

    grnForm.value.items.forEach((item, i) => {
        if (item.ingredient_id) {
            formData.append(
                `items[${i}][ingredient_id]`,
                String(item.ingredient_id),
            );
        }

        formData.append(`items[${i}][unit_label]`, item.unit_label.trim());
        formData.append(`items[${i}][actual_qty]`, String(item.actual_qty));
        formData.append(`items[${i}][unit_cost]`, String(item.unit_cost));

        if (item.lot_number) {
            formData.append(`items[${i}][lot_number]`, item.lot_number);
        }

        if (item.manufactured_date) {
            formData.append(
                `items[${i}][manufactured_date]`,
                item.manufactured_date,
            );
        }

        if (item.expiry_date) {
            formData.append(`items[${i}][expiry_date]`, item.expiry_date);
        }

        if (item.location_id) {
            formData.append(
                `items[${i}][location_id]`,
                String(item.location_id),
            );
        }

    });

    grnFiles.value.forEach((f) => {
        formData.append('evidence[]', f);
        formData.append('evidence_types[]', 'external_record');
    });

    try {
        const { data } = await axios.post(
            '/api/warehouse/receiving-vouchers',
            formData,
            {
                headers: { 'Content-Type': 'multipart/form-data' },
            },
        );
        toast.success(data.message || 'Tạo phiếu nhập ngoài thành công.');

        if (data.voucher) {
            voucherList.value.unshift(data.voucher);
        }

        grnForm.value.items = [];
        grnForm.value.external_receipt_reason = 'other';
        grnForm.value.external_source_name = '';
        grnForm.value.external_reference = '';
        grnForm.value.verification_assigned_to = null;
        addGrnItem();
        grnFiles.value = [];
        activeTab.value = 'today';
    } catch (e: any) {
        const errors = e.response?.data?.errors;
        let errorMessage = e.response?.data?.message;

        if (errors) {
            const firstKey = Object.keys(errors)[0];

            if (firstKey && errors[firstKey]?.[0]) {
                errorMessage = errors[firstKey][0];
            }
        }

        toast.error(errorMessage ?? 'Lỗi tạo phiếu nhập ngoài.');
    } finally {
        isSubmittingGrn.value = false;
    }
}

// Incident
async function submitIncident() {
    if (!incidentForm.value.description) {
        toast.error('Vui lòng mô tả sự cố.');

        return;
    }

    if (
        ['damage', 'expired'].includes(incidentForm.value.incident_type) &&
        incidentForm.value.batch_id &&
        (incidentForm.value.quantity_affected ?? 0) > 0 &&
        !window.confirm(
            'Báo cáo này sẽ chuyển số lượng batch sang khu cách ly và trừ tồn khả dụng. Bạn có chắc chắn không?',
        )
    ) {
        return;
    }

    isSubmittingIncident.value = true;
    const formData = new FormData();
    const idempotencyKey =
        typeof crypto !== 'undefined' && crypto.randomUUID
            ? crypto.randomUUID()
            : `incident-${Date.now()}-${Math.random().toString(16).slice(2)}`;
    formData.append('incident_type', incidentForm.value.incident_type);
    formData.append('idempotency_key', idempotencyKey);
    formData.append('description', incidentForm.value.description);

    if (incidentForm.value.ingredient_id) {
        formData.append(
            'ingredient_id',
            String(incidentForm.value.ingredient_id),
        );
    }

    if (incidentForm.value.batch_id) {
        formData.append('batch_id', String(incidentForm.value.batch_id));
    }

    if (incidentForm.value.location_id) {
        formData.append('location_id', String(incidentForm.value.location_id));
    }

    if (
        incidentForm.value.quantity_affected !== null &&
        incidentForm.value.quantity_affected !== undefined
    ) {
        formData.append(
            'quantity_affected',
            String(incidentForm.value.quantity_affected),
        );
    }

    incidentFiles.value.forEach((f) => formData.append('evidence[]', f));

    try {
        const { data } = await axios.post(
            '/api/warehouse/incidents',
            formData,
            {
                headers: { 'Content-Type': 'multipart/form-data' },
            },
        );
        toast.success(data.message || 'Đã gửi báo cáo sự cố kho thành công.');
        incidentForm.value = {
            incident_type: 'shortage',
            description: '',
            ingredient_id: null,
            batch_id: undefined,
            location_id: undefined,
            quantity_affected: undefined,
        };
        incidentFiles.value = [];
        activeTab.value = 'today';
    } catch (e: any) {
        toast.error(e.response?.data?.message ?? 'Lỗi khi gửi báo cáo sự cố.');
    } finally {
        isSubmittingIncident.value = false;
    }
}

// Shift handover
async function submitHandover() {
    if (!handoverForm.value.received_by) {
        toast.error('Hãy chọn người nhận ca trước khi nộp biên bản.');

        return;
    }

    isSubmittingHandover.value = true;

    try {
        const { data } = await axios.post(
            '/api/warehouse/shift-handover',
            handoverForm.value,
        );
        toast.success(data.message || 'Đã nộp biên bản bàn giao ca.');

        if (data.is_system_locked) {
            toast.warning(
                `Cảnh báo: Còn ${data.pending_tasks} task chưa hoàn thành trong ca.`,
            );
        }

        if (data.handover) {
            handoverList.value.unshift(data.handover);
        }
    } catch (e: any) {
        toast.error(e.response?.data?.message ?? 'Lỗi bàn giao ca.');
    } finally {
        isSubmittingHandover.value = false;
    }
}

async function respondToDispute(dispute: any) {
    const response = prompt(
        'Nhập ý kiến phản hồi / bằng chứng đối với biên bản ' +
            dispute.dispute_code +
            ':',
    );

    if (!response?.trim()) {
        return;
    }

    try {
        await axios.post(
            `/api/warehouse-governance/disputes/${dispute.id}/respond`,
            { response: response.trim() },
        );
        toast.success('Đã gửi phản hồi tranh chấp.');
        disputeList.value = disputeList.value.filter(
            (item) => item.id !== dispute.id,
        );
    } catch (e: any) {
        toast.error(
            e.response?.data?.message ?? 'Không thể gửi phản hồi tranh chấp.',
        );
    }
}

async function confirmReceivingReport(report: any) {
    const notes = prompt(
        `Xác nhận bạn đã đọc biên bản ${report.report_code}. Có thể ghi chú thêm (không bắt buộc):`,
    );

    if (notes === null) {
        return;
    }

    try {
        const { data } = await axios.post(
            `/api/receiving-reports/${report.id}/driver-confirm`,
            { notes: notes.trim() || null },
        );
        toast.success(
            data.message || 'Đã xác nhận biên bản nhận hàng với tư cách tài xế.',
        );
        receivingReportList.value = receivingReportList.value.map((item) =>
            item.id === report.id
                ? { ...item, status: 'driver_confirmed', driver_confirmed_at: new Date().toISOString() }
                : item,
        );
    } catch (e: any) {
        toast.error(
            e.response?.data?.message || 'Không thể xác nhận biên bản nhận hàng.',
        );
    }
}

async function confirmHandover(handoverId: number) {
    try {
        await axios.post(
            `/api/warehouse/shift-handover/${handoverId}/confirm`,
            {},
        );
        toast.success('Đã xác nhận bàn giao ca.');
        handoverList.value = handoverList.value.map((item) =>
            item.id === handoverId ? { ...item, status: 'confirmed' } : item,
        );
    } catch (e: any) {
        toast.error(
            e.response?.data?.message ?? 'Không thể xác nhận bàn giao ca.',
        );
    }
}

async function markNotificationRead(notification: any) {
    const targetUrl = notification.data?.url;

    try {
        await axios.post(`/notifications/${notification.id}/read`);
        notificationList.value = notificationList.value.filter(
            (item) => item.id !== notification.id,
        );

        if (targetUrl) {
            window.location.assign(targetUrl);
        }
    } catch {
        // Notification failures must not block warehouse work.
    }
}

async function confirmPutaway(task: any) {
    const locationId = window.prompt('Nhập ID vị trí đã quét:');

    if (!locationId || Number.isNaN(Number(locationId))) {
        return;
    }

    const putawayItems = task.receiving_voucher?.items ?? [];
    const defaultBatchId =
        putawayItems.length === 1 ? putawayItems[0].batch_id : null;
    const batchInput =
        defaultBatchId || window.prompt('Nhập ID batch/lô cần cất:', '');

    if (
        putawayItems.length > 1 &&
        (!batchInput || Number.isNaN(Number(batchInput)))
    ) {
        return;
    }

    try {
        await axios.post(`/api/warehouse/tasks/${task.id}/putaway-confirm`, {
            location_id: Number(locationId),
            batch_id: batchInput ? Number(batchInput) : undefined,
            scan_log: [
                {
                    code: locationId,
                    type: 'location',
                    scanned_at: new Date().toISOString(),
                },
            ],
        });
        toast.success('Đã xác nhận cất hàng.');
        await refreshTasks(true);
    } catch (e: any) {
        toast.error(
            e.response?.data?.message ?? 'Không thể xác nhận cất hàng.',
        );
    }
}

async function openPickingModal(task: any) {
    let items = task.supply_request?.items ?? [];

    if (!task.supply_request?.id) {
        toast.error('Task soạn hàng chưa có dữ liệu đơn cấp phát.');

        return;
    }

    if (items.length === 0) {
        try {
            const { data } = await axios.get(
                `/api/supply-requests/${task.supply_request.id}`,
            );

            if (data.data?.items) {
                items = data.data.items;
                task.supply_request.items = items;
            }
        } catch {
            // fallback
        }
    }

    if (items.length === 0) {
        toast.error('Không tìm thấy danh sách nguyên liệu cần soạn.');

        return;
    }

    activePickingTask.value = task;
    pickingFormItems.value = items.map((item: any) => ({
        id: item.id,
        ingredient_name:
            item.ingredient_name || item.ingredient?.name || 'Nguyên liệu',
        approved_quantity: Number(
            item.approved_quantity ?? item.requested_quantity ?? item.quantity ?? 1,
        ),
        actual_dispatched_quantity: Number(
            item.actual_dispatched_quantity ??
                item.approved_quantity ??
                item.requested_quantity ??
                item.quantity ??
                1,
        ),
        batch_id: item.batch_id ?? null,
        warehouse_location_id: item.warehouse_location_id ?? null,
        unit_symbol:
            item.unit_symbol ||
            item.unit ||
            item.ingredient?.unit?.symbol ||
            'đv',
    }));
    showPickingModal.value = true;
}

async function submitPickingModal() {
    if (!activePickingTask.value?.supply_request?.id) {
        return;
    }

    const invalidItem = pickingFormItems.value.find(
        (item) => item.actual_dispatched_quantity < 0,
    );

    if (invalidItem) {
        toast.error(
            `Số lượng soạn cho ${invalidItem.ingredient_name} không hợp lệ.`,
        );

        return;
    }

    isSubmittingPicking.value = true;

    try {
        const preparedItems = pickingFormItems.value.map((item) => ({
            id: item.id,
            actual_dispatched_quantity: Number(item.actual_dispatched_quantity),
            batch_id: item.batch_id ? Number(item.batch_id) : null,
            warehouse_location_id: item.warehouse_location_id
                ? Number(item.warehouse_location_id)
                : null,
        }));

        await axios.post(
            `/api/supply-requests/${activePickingTask.value.supply_request.id}/prepare`,
            {
                items: preparedItems,
            },
        );

        toast.success('Đã ghi nhận hoàn tất soạn hàng FEFO thành công!');
        showPickingModal.value = false;
        activePickingTask.value = null;
        await refreshTasks(true);
    } catch (e: any) {
        toast.error(
            e.response?.data?.message ?? 'Không thể ghi nhận soạn hàng.',
        );
    } finally {
        isSubmittingPicking.value = false;
    }
}

async function dispatchHandoverTask(task: any) {
    if (!task.supply_request?.id) {
        toast.error('Task bàn giao chưa có đơn cấp phát.');

        return;
    }

    const sealCode = window.prompt(
        'Nhập mã niêm phong trước khi bàn giao:',
        '',
    );

    if (sealCode === null) {
        return;
    }

    try {
        await axios.post(
            `/api/supply-requests/${task.supply_request.id}/dispatch`,
            { seal_code: sealCode || null },
        );
        toast.success('Đã ghi nhận bàn giao Kho Tổng.');
        await refreshTasks(true);
    } catch (e: any) {
        toast.error(
            e.response?.data?.message ?? 'Không thể bàn giao đơn cấp phát.',
        );
    }
}

function openTaskCompletion(task: any) {
    if (task.task_type === 'putaway') {
        return confirmPutaway(task);
    }

    if (task.task_type === 'picking') {
        return openPickingModal(task);
    }

    if (task.task_type === 'handover') {
        return dispatchHandoverTask(task);
    }

    if (task.task_type === 'packing') {
        const packingNote = window.prompt(
            'Nhập số kiện/carton, seal và ghi chú đóng gói:',
        );

        if (packingNote === null) {
            return;
        }

        taskResultNote.value = packingNote;
    }

    activeTaskId.value = task.id;
}

// Scan
async function handleScan() {
    if (!scanInput.value.trim()) {
        return;
    }

    isScanLoading.value = true;
    scanResult.value = null;

    try {
        const { data } = await axios.post('/api/warehouse/scan', {
            code: scanInput.value.trim(),
        });
        scanResult.value = data;

        if (data.warning) {
            toast.warning(data.warning);
        }
    } catch {
        scanResult.value = {
            type: 'unknown',
            status: 'not_found',
            message: 'Không tìm thấy thông tin mã quét trong hệ thống.',
        };
    } finally {
        isScanLoading.value = false;
    }
}

function handleFileInput(event: Event, target: 'grn' | 'incident' | 'task') {
    const files = (event.target as HTMLInputElement).files;

    if (!files) {
        return;
    }

    const incoming = Array.from(files);

    if (target === 'grn') {
        const existing = new Set(grnFiles.value.map((f) => `${f.name}-${f.size}-${f.lastModified}`));
        const unique = incoming.filter((f) => !existing.has(`${f.name}-${f.size}-${f.lastModified}`));
        grnFiles.value = [...grnFiles.value, ...unique];
    } else if (target === 'incident') {
        const existing = new Set(incidentFiles.value.map((f) => `${f.name}-${f.size}-${f.lastModified}`));
        const unique = incoming.filter((f) => !existing.has(`${f.name}-${f.size}-${f.lastModified}`));
        incidentFiles.value = [...incidentFiles.value, ...unique];
    } else {
        const existing = new Set(taskFiles.value.map((f) => `${f.name}-${f.size}-${f.lastModified}`));
        const unique = incoming.filter((f) => !existing.has(`${f.name}-${f.size}-${f.lastModified}`));
        taskFiles.value = [...taskFiles.value, ...unique];
    }
}

function removeFile(index: number, target: 'grn' | 'incident' | 'task') {
    if (target === 'grn') {
        grnFiles.value.splice(index, 1);
    } else if (target === 'incident') {
        incidentFiles.value.splice(index, 1);
    } else {
        taskFiles.value.splice(index, 1);
    }
}

async function startCameraScan() {
    cameraError.value = '';

    if (
        !('BarcodeDetector' in window) ||
        !navigator.mediaDevices?.getUserMedia
    ) {
        cameraError.value =
            'Trình duyệt chưa hỗ trợ quét camera. Hãy nhập mã thủ công.';

        return;
    }

    try {
        cameraStream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: { ideal: 'environment' } },
        });
        isCameraScanning.value = true;
        const video = document.querySelector<HTMLVideoElement>(
            '#warehouse-scan-video',
        );

        if (!video) {
            return;
        }

        video.srcObject = cameraStream;
        await video.play();
        const detector = new (window as any).BarcodeDetector({
            formats: ['qr_code', 'code_128', 'ean_13', 'ean_8'],
        });
        const scanFrame = async () => {
            if (!isCameraScanning.value) {
                return;
            }

            try {
                const detected = await detector.detect(video);

                if (detected?.[0]?.rawValue) {
                    scanInput.value = detected[0].rawValue;
                    stopCameraScan();
                    await handleScan();

                    return;
                }
            } catch {
                // Continue scanning; unsupported formats are handled by the manual field.
            }

            window.requestAnimationFrame(scanFrame);
        };
        window.requestAnimationFrame(scanFrame);
    } catch (error: any) {
        cameraError.value = error?.message ?? 'Không thể mở camera.';
        stopCameraScan();
    }
}

function stopCameraScan() {
    isCameraScanning.value = false;
    cameraStream?.getTracks().forEach((track) => track.stop());
    cameraStream = null;
}

onMounted(() => {
    if (grnForm.value.items.length === 0) {
        addGrnItem();
    }

    const requestedTab = new URLSearchParams(window.location.search).get('tab');

    if (
        requestedTab &&
        ['today', 'receiving', 'putaway', 'picking', 'packing', 'counting', 'incident', 'handover', 'delivery'].includes(requestedTab)
    ) {
        activeTab.value = requestedTab as TabId;
    } else if (assignedVerificationList.value.length > 0) {
        // Phiếu được giao phải hiện ngay khi nhân viên mở cổng, không bị ẩn
        // sau tab "Việc hôm nay" (nơi chỉ hiển thị warehouse_task_assignments).
        activeTab.value = 'receiving';
    }

    const voucherId = Number(new URLSearchParams(window.location.search).get('voucher'));
    const reportId = Number(new URLSearchParams(window.location.search).get('report_id'));

    if (requestedTab === 'receiving' && voucherId) {
        const voucher = assignedVerificationList.value.find((item) => item.id === voucherId);

        if (voucher) {
            openVerification(voucher);
        }
    }

    if (requestedTab === 'incident' && reportId) {
        highlightedWarehouseReportId.value = reportId;
    }

    // Tự refresh task mỗi 5 phút
    refreshTimer = setInterval(() => refreshTasks(true), 5 * 60 * 1000);

    setTimeout(checkTabScroll, 150);

    if (typeof window !== 'undefined') {
        window.addEventListener('resize', checkTabScroll);
    }
});

onBeforeUnmount(() => {
    if (refreshTimer) {
        clearInterval(refreshTimer);
    }

    if (typeof window !== 'undefined') {
        window.removeEventListener('resize', checkTabScroll);
    }

    stopCameraScan();
});
</script>

<template>
    <Head title="Cổng Nhân Sự & Tác Vụ Kho - Aventura" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6">
        <!-- ── Page Header ── -->
        <div
            class="flex flex-col gap-4 border-b border-border/70 pb-5 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-center gap-3.5">
                <div
                    class="flex size-11 items-center justify-center rounded-xl border border-primary/20 bg-primary/10 text-primary shadow-xs"
                >
                    <CheckSquare class="size-5" />
                </div>
                <div>
                    <h1
                        class="text-xl font-bold tracking-tight text-foreground sm:text-2xl"
                    >
                        Cổng Tác Vụ Nhân Viên Kho
                    </h1>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        {{ centralBranch?.name || 'Kho Tổng Sài Gòn' }}
                        <span class="mx-1.5 opacity-40">•</span>
                        Người thực thi:
                        <span class="font-medium text-foreground">{{
                            currentUser?.name
                        }}</span>
                        <span class="text-muted-foreground/80">
                            ({{ currentUser?.job_title || 'Nhân viên kho' }})</span
                        >
                    </p>
                </div>
            </div>

            <!-- Quick Action Toolbar -->
            <div class="flex flex-wrap items-center gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    class="h-9 gap-1.5 border-border/80 text-xs font-medium shadow-xs hover:bg-muted/60"
                    @click="showScanModal = true"
                >
                    <QrCode class="size-3.5 text-muted-foreground" />
                    <span>Quét mã</span>
                </Button>

                <Button
                    variant="outline"
                    size="sm"
                    class="h-9 gap-1.5 border-border/80 text-xs font-medium shadow-xs hover:bg-muted/60"
                    :disabled="isLoading"
                    @click="refreshTasks(false)"
                >
                    <RefreshCw
                        class="size-3.5 text-muted-foreground"
                        :class="{ 'animate-spin': isLoading }"
                    />
                    <span class="hidden sm:inline">Làm mới</span>
                </Button>

                <Button
                    variant="outline"
                    size="sm"
                    class="relative h-9 gap-1.5 border-border/80 text-xs font-medium shadow-xs hover:bg-muted/60"
                    :class="{ 'bg-muted/80': showNotifications }"
                    @click="showNotifications = !showNotifications; showHistory = false"
                >
                    <Bell class="size-3.5 text-muted-foreground" />
                    <span class="hidden sm:inline">Thông báo</span>
                    <span
                        v-if="notificationList.length > 0"
                        class="flex size-4 items-center justify-center rounded-full bg-rose-500 text-[10px] font-bold text-white leading-none"
                    >
                        {{ notificationList.length }}
                    </span>
                </Button>

                <Button
                    variant="outline"
                    size="sm"
                    class="h-9 gap-1.5 border-border/80 text-xs font-medium shadow-xs hover:bg-muted/60"
                    :class="{ 'bg-muted/80': showHistory }"
                    @click="showHistory = !showHistory; showNotifications = false"
                >
                    <History class="size-3.5 text-muted-foreground" />
                    <span class="hidden sm:inline">Lịch sử</span>
                </Button>

                <Link v-if="canManageWarehouse" href="/warehouse/team">
                    <Button
                        variant="secondary"
                        size="sm"
                        class="h-9 gap-1.5 text-xs font-medium"
                    >
                        <Users class="size-3.5" />
                        <span>Đội ngũ</span>
                    </Button>
                </Link>
            </div>
        </div>

        <!-- ── Notifications Drawer ── -->
        <Card
            v-if="showNotifications"
            class="border-border/80 bg-card shadow-sm"
        >
            <CardHeader class="flex flex-row items-center justify-between py-3">
                <div>
                    <CardTitle class="text-sm font-semibold">Thông báo công việc</CardTitle>
                    <CardDescription class="text-xs"
                        >Task mới, bàn giao ca và kiểm kê cần xử lý.</CardDescription
                    >
                </div>
                <Button
                    size="sm"
                    variant="ghost"
                    @click="showNotifications = false"
                    >Đóng</Button
                >
            </CardHeader>
            <CardContent class="space-y-2 pt-0">
                <div
                    v-if="notificationList.length === 0"
                    class="py-4 text-center text-xs text-muted-foreground"
                >
                    Không có thông báo chưa đọc.
                </div>
                <button
                    v-for="notification in notificationList"
                    :key="notification.id"
                    class="flex w-full items-start justify-between gap-3 rounded-lg border border-border/60 bg-muted/20 p-3 text-left text-xs transition hover:bg-muted/40"
                    @click="markNotificationRead(notification)"
                >
                    <span
                        ><strong class="text-foreground">{{
                            notification.data?.title ||
                            notification.data?.message ||
                            'Thông báo Kho Tổng'
                        }}</strong
                        ><br /><span class="text-muted-foreground">{{
                            notification.data?.message
                        }}</span></span
                    >
                    <CheckCircle class="size-4 shrink-0 text-emerald-500" />
                </button>
            </CardContent>
        </Card>

        <!-- ── History Drawer ── -->
        <Card
            v-if="showHistory"
            class="border-border/80 bg-card shadow-sm"
        >
            <CardHeader class="flex flex-row items-center justify-between py-3">
                <CardTitle class="text-sm font-semibold">Lịch sử thao tác của tôi</CardTitle>
                <Button size="sm" variant="ghost" @click="showHistory = false"
                    >Đóng</Button
                >
            </CardHeader>
            <CardContent class="space-y-2 pt-0">
                <div
                    v-if="historyList.length === 0"
                    class="py-4 text-center text-xs text-muted-foreground"
                >
                    Chưa có dữ liệu lịch sử trong phiên này.
                </div>
                <div
                    v-for="item in historyList"
                    :key="item.id"
                    class="flex items-center justify-between rounded-lg border border-border/60 bg-muted/20 p-2.5 text-xs"
                >
                    <span class="font-medium text-foreground">{{ item.action }}</span>
                    <span class="text-muted-foreground">{{
                        new Date(item.created_at).toLocaleString('vi-VN')
                    }}</span>
                </div>
            </CardContent>
        </Card>

        <!-- ── Summary Metric Cards (Clean, Refined, Non-garish) ── -->
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-2 lg:grid-cols-4 sm:gap-4">
            <!-- 1. Đang thực hiện -->
            <div
                class="motion-card animate-fade-in-up stagger-1 group relative overflow-hidden rounded-xl border border-border/70 bg-card p-4 shadow-xs transition hover:border-border hover:shadow-sm"
            >
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-muted-foreground">Đang thực hiện</span>
                    <div
                        class="flex size-7 items-center justify-center rounded-md bg-amber-500/10 text-amber-600 dark:text-amber-400 transition-transform duration-200 group-hover:scale-110"
                    >
                        <Zap class="size-3.5" />
                    </div>
                </div>
                <div class="mt-2.5 flex items-baseline gap-2">
                    <span
                        class="text-2xl sm:text-3xl font-bold tracking-tight text-foreground tabular-nums"
                    >
                        {{ taskSummaryData.in_progress }}
                    </span>
                    <span class="text-[11px] text-muted-foreground">tác vụ</span>
                </div>
                <p class="mt-1 text-[11px] text-muted-foreground/80 truncate">
                    Đang tiến hành xử lý
                </p>
                <div class="absolute bottom-0 left-0 right-0 h-0.5 bg-amber-500/50"></div>
            </div>

            <!-- 2. Chờ xử lý -->
            <div
                class="motion-card animate-fade-in-up stagger-2 group relative overflow-hidden rounded-xl border border-border/70 bg-card p-4 shadow-xs transition hover:border-border hover:shadow-sm"
            >
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-muted-foreground">Chờ xử lý</span>
                    <div
                        class="flex size-7 items-center justify-center rounded-md bg-sky-500/10 text-sky-600 dark:text-sky-400 transition-transform duration-200 group-hover:scale-110"
                    >
                        <Clock class="size-3.5" />
                    </div>
                </div>
                <div class="mt-2.5 flex items-baseline gap-2">
                    <span
                        class="text-2xl sm:text-3xl font-bold tracking-tight text-foreground tabular-nums"
                    >
                        {{ taskSummaryData.pending }}
                    </span>
                    <span class="text-[11px] text-muted-foreground">tác vụ</span>
                </div>
                <p class="mt-1 text-[11px] text-muted-foreground/80 truncate">
                    Chờ bắt đầu thực hiện
                </p>
                <div class="absolute bottom-0 left-0 right-0 h-0.5 bg-sky-500/50"></div>
            </div>

            <!-- 3. Tác vụ quá hạn -->
            <div
                class="motion-card animate-fade-in-up stagger-3 group relative overflow-hidden rounded-xl border border-border/70 bg-card p-4 shadow-xs transition hover:border-border hover:shadow-sm"
            >
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-muted-foreground">Tác vụ quá hạn</span>
                    <div
                        class="flex size-7 items-center justify-center rounded-md bg-rose-500/10 text-rose-600 dark:text-rose-400 transition-transform duration-200 group-hover:scale-110"
                    >
                        <AlertTriangle class="size-3.5" />
                    </div>
                </div>
                <div class="mt-2.5 flex items-baseline gap-2">
                    <span
                        class="text-2xl sm:text-3xl font-bold tracking-tight tabular-nums"
                        :class="overdueCount > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-foreground'"
                    >
                        {{ overdueCount }}
                    </span>
                    <span class="text-[11px] text-muted-foreground">cần gấp</span>
                </div>
                <p class="mt-1 text-[11px] text-muted-foreground/80 truncate">
                    {{ overdueCount > 0 ? 'Cần ưu tiên xử lý ngay' : 'Không có tác vụ trễ' }}
                </p>
                <div
                    class="absolute bottom-0 left-0 right-0 h-0.5"
                    :class="overdueCount > 0 ? 'bg-rose-500' : 'bg-rose-500/30'"
                ></div>
            </div>

            <!-- 4. Hoàn thành hôm nay -->
            <div
                class="motion-card animate-fade-in-up stagger-4 group relative overflow-hidden rounded-xl border border-border/70 bg-card p-4 shadow-xs transition hover:border-border hover:shadow-sm"
            >
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-muted-foreground">Hoàn thành hôm nay</span>
                    <div
                        class="flex size-7 items-center justify-center rounded-md bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 transition-transform duration-200 group-hover:scale-110"
                    >
                        <CheckCircle class="size-3.5" />
                    </div>
                </div>
                <div class="mt-2.5 flex items-baseline gap-2">
                    <span
                        class="text-2xl sm:text-3xl font-bold tracking-tight text-foreground tabular-nums"
                    >
                        {{ taskSummaryData.completed_today ?? 0 }}
                    </span>
                    <span class="text-[11px] text-muted-foreground">đã xong</span>
                </div>
                <p class="mt-1 text-[11px] text-muted-foreground/80 truncate">
                    Tác vụ đã hoàn tất chuẩn
                </p>
                <div class="absolute bottom-0 left-0 right-0 h-0.5 bg-emerald-500/50"></div>
            </div>
        </div>

        <div class="relative flex items-center">
            <button
                v-if="canScrollLeft"
                type="button"
                class="absolute -left-2 z-10 flex size-7 items-center justify-center rounded-full border border-border/80 bg-background/95 text-muted-foreground shadow-xs hover:text-foreground backdrop-blur-xs"
                @click="scrollTabs('left')"
            >
                <ChevronLeft class="size-3.5" />
            </button>

            <div
                ref="tabsContainer"
                class="flex w-full items-center gap-1.5 overflow-x-auto rounded-xl border border-border/70 bg-muted/40 p-1.5 scrollbar-none"
                @wheel="handleTabsWheel"
                @scroll="checkTabScroll"
            >
                <button
                    v-for="tab in tabs"
                    :key="tab.id"
                    :ref="(el) => setTabRef(el, tab.id)"
                    class="flex shrink-0 items-center gap-2 rounded-lg px-3.5 py-2 text-xs font-medium transition-all"
                    :class="
                        activeTab === tab.id
                            ? 'bg-background text-foreground shadow-xs border border-border/60 font-semibold'
                            : 'text-muted-foreground hover:bg-background/60 hover:text-foreground'
                    "
                    @click="selectTab(tab.id)"
                >
                    <component
                        :is="tab.icon"
                        class="size-3.5 shrink-0"
                        :class="activeTab === tab.id ? 'text-primary' : 'text-muted-foreground/70'"
                    />
                    <span>{{ tab.label }}</span>
                    <span
                        v-if="tab.count > 0"
                        class="ml-1 rounded-full px-1.5 py-0.2 text-[10px] font-semibold tabular-nums"
                        :class="
                            tab.id === 'incident'
                                ? 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20'
                                : activeTab === tab.id
                                  ? 'bg-muted text-foreground font-bold'
                                  : 'bg-muted/80 text-muted-foreground'
                        "
                    >
                        {{ tab.count }}
                    </span>
                </button>
            </div>

            <button
                v-if="canScrollRight"
                type="button"
                class="absolute -right-2 z-10 flex size-7 items-center justify-center rounded-full border border-border/80 bg-background/95 text-muted-foreground shadow-xs hover:text-foreground backdrop-blur-xs"
                @click="scrollTabs('right')"
            >
                <ChevronRight class="size-3.5" />
            </button>
        </div>

        <!-- ── Tab Contents ── -->

        <!-- 1. HÔM NAY / VIỆC CỦA TÔI -->
        <div v-if="activeTab === 'today'" class="flex flex-col gap-4">
            <!-- Filter Bar -->
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-base font-bold text-foreground">
                        Danh Sách Tác Vụ Của Tôi
                    </h2>
                    <p class="text-xs text-muted-foreground mt-0.5">
                        Các công việc do Trưởng kho phân công trực tiếp cho bạn
                    </p>
                </div>

                <!-- Status Filter Pills -->
                <div class="flex flex-wrap items-center gap-1.5">
                    <button
                        type="button"
                        v-for="filter in [
                            { id: 'all', label: 'Tất cả', count: taskFilterCounts.all },
                            { id: 'in_progress', label: 'Đang làm', count: taskFilterCounts.in_progress },
                            { id: 'assigned', label: 'Chờ xử lý', count: taskFilterCounts.assigned },
                            { id: 'overdue', label: 'Quá hạn', count: taskFilterCounts.overdue },
                            { id: 'completed', label: 'Đã xong', count: taskFilterCounts.completed },
                        ]"
                        :key="filter.id"
                        class="flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium transition-all"
                        :class="
                            taskStatusFilter === filter.id
                                ? 'bg-foreground text-background shadow-xs font-semibold'
                                : 'bg-muted/60 text-muted-foreground hover:bg-muted hover:text-foreground'
                        "
                        @click="taskStatusFilter = filter.id as any"
                    >
                        <span>{{ filter.label }}</span>
                        <span
                            class="rounded-full px-1.5 py-0.2 text-[10px] font-semibold tabular-nums"
                            :class="
                                taskStatusFilter === filter.id
                                    ? 'bg-background/20 text-background'
                                    : 'bg-background text-muted-foreground border border-border/50'
                            "
                        >
                            {{ filter.count }}
                        </span>
                    </button>
                </div>
            </div>

            <!-- Empty State -->
            <div
                v-if="filteredTasks.length === 0"
                class="flex flex-col items-center justify-center rounded-xl border border-dashed border-border p-12 text-center bg-card/50"
            >
                <div
                    class="flex size-12 items-center justify-center rounded-xl bg-muted text-muted-foreground"
                >
                    <Warehouse class="size-6" />
                </div>
                <h3
                    class="mt-3 text-sm font-semibold text-foreground"
                >
                    {{ taskStatusFilter !== 'all' ? 'Không có tác vụ phù hợp bộ lọc' : 'Không có tác vụ nào đang chờ' }}
                </h3>
                <p
                    class="mt-1 max-w-sm text-xs text-muted-foreground"
                >
                    {{ taskStatusFilter !== 'all' ? 'Hãy chọn mục lọc khác để xem thêm công việc.' : 'Hiện tại bạn đã hoàn thành hết các nhiệm vụ được giao.' }}
                </p>
                <Button
                    v-if="taskStatusFilter !== 'all'"
                    variant="outline"
                    size="sm"
                    class="mt-3 text-xs"
                    @click="taskStatusFilter = 'all'"
                >
                    Xem tất cả tác vụ
                </Button>
            </div>

            <!-- Tasks Grid -->
            <div
                v-else
                class="grid grid-cols-1 gap-3.5 md:grid-cols-2 lg:grid-cols-3"
            >
                <Card
                    v-for="(task, tIdx) in filteredTasks"
                    :key="task.id"
                    class="motion-card animate-fade-in-up relative flex flex-col justify-between overflow-hidden border border-border/70 bg-card text-card-foreground shadow-xs transition hover:border-border hover:shadow-sm"
                    :class="[
                        `stagger-${(tIdx % 6) + 1}`,
                        {
                            'border-l-4 border-l-rose-500': task.is_overdue || (task.due_at && new Date(task.due_at) < new Date() && !['completed', 'cancelled'].includes(task.status)),
                            'border-l-4 border-l-amber-500': task.status === 'in_progress' && !task.is_overdue,
                            'border-l-4 border-l-emerald-500': task.status === 'completed',
                        },
                    ]"
                >
                    <div class="p-4 sm:p-5">
                        <div class="flex items-start justify-between gap-2">
                            <Badge
                                variant="outline"
                                class="gap-1.5 text-[11px] font-medium"
                                :class="priorityBadgeClass(task.priority)"
                            >
                                <span
                                    class="size-1.5 rounded-full"
                                    :class="
                                        task.priority === 'urgent' ||
                                        task.priority === 'high'
                                            ? 'bg-rose-500'
                                            : 'bg-muted-foreground'
                                    "
                                ></span>
                                {{ taskTypeLabel(task.task_type) }}
                            </Badge>
                            <Badge
                                variant="outline"
                                class="text-[11px] font-medium"
                                :class="statusBadgeClass(task.status)"
                            >
                                {{ statusLabel(task.status) }}
                            </Badge>
                        </div>

                        <div
                            v-if="task.supply_request"
                            class="mt-3 flex items-center gap-1.5 text-xs font-semibold text-foreground"
                        >
                            <span class="font-mono">{{ task.supply_request.request_code }}</span>
                            <ChevronRight class="size-3.5 text-muted-foreground" />
                            <span class="text-muted-foreground font-normal">{{
                                formatBranchName(task.supply_request.to_branch)
                            }}</span>
                        </div>

                        <p
                            v-if="task.notes"
                            class="mt-2 text-xs leading-relaxed text-muted-foreground line-clamp-2"
                        >
                            {{ task.notes }}
                        </p>

                        <div
                            class="mt-3.5 flex flex-wrap items-center gap-3 border-t border-border/60 pt-3 text-[11px] text-muted-foreground"
                        >
                            <span
                                v-if="task.due_at"
                                class="flex items-center gap-1"
                                :class="{
                                    'font-semibold text-rose-600 dark:text-rose-400':
                                        task.is_overdue,
                                }"
                            >
                                <Clock class="size-3.5" />
                                Hạn:
                                {{
                                    new Date(task.due_at).toLocaleString(
                                        'vi-VN',
                                        {
                                            hour: '2-digit',
                                            minute: '2-digit',
                                            day: '2-digit',
                                            month: '2-digit',
                                        },
                                    )
                                }}
                            </span>
                            <span class="flex items-center gap-1">
                                <HardHat class="size-3.5 text-muted-foreground/70" />
                                {{ priorityLabel(task.priority) }}
                            </span>
                        </div>
                        <div
                            v-if="task.evidence_urls?.length"
                            class="mt-3 flex flex-wrap gap-1.5"
                        >
                            <a
                                v-for="(url, index) in task.evidence_urls"
                                :key="url"
                                :href="url"
                                target="_blank"
                                rel="noopener"
                                class="rounded-md border border-border px-2 py-0.5 text-[11px] text-muted-foreground hover:bg-muted hover:text-foreground"
                            >
                                Chứng từ {{ Number(index) + 1 }}
                            </a>
                        </div>
                    </div>

                    <div
                        class="border-t border-border/60 bg-muted/20 p-3"
                    >
                        <div class="flex items-center justify-end gap-2">
                            <Button
                                v-if="task.status === 'assigned'"
                                size="sm"
                                class="w-full gap-1.5 text-xs font-medium"
                                :disabled="isProcessingTask"
                                @click="startTask(task)"
                            >
                                <ArrowRight class="size-3.5" /> Bắt đầu thực hiện
                            </Button>
                            <Button
                                v-if="task.status === 'in_progress'"
                                size="sm"
                                class="w-full gap-1.5 bg-emerald-600 text-xs font-medium text-white shadow-xs hover:bg-emerald-700 dark:bg-emerald-600 dark:hover:bg-emerald-700"
                                @click="openTaskCompletion(task)"
                            >
                                <ClipboardList class="size-3.5" />
                                {{ getTaskActionButtonLabel(task) }}
                            </Button>
                            <div
                                v-if="task.status === 'completed'"
                                class="flex w-full items-center justify-center gap-1 py-1 text-xs font-medium text-emerald-600 dark:text-emerald-400"
                            >
                                <BadgeCheck class="size-4" /> Đã hoàn thành
                            </div>
                        </div>
                    </div>
                </Card>
            </div>
        </div>

        <!-- 2. GIAO HÀNG TỚI CHI NHÁNH -->
        <div v-if="activeTab === 'delivery'" class="flex flex-col gap-4">
            <div>
                <h2
                    class="text-base font-bold text-foreground"
                >
                    Giao hàng tới Chi nhánh
                </h2>
                <p class="text-xs text-muted-foreground mt-0.5">
                    Hoàn tất giao thực tế rồi bấm “Giao hàng thành công” để
                    Quản lý chi nhánh được kiểm đếm và nghiệm thu.
                </p>
            </div>

            <div
                v-if="tasksByType('delivery').length === 0"
                class="rounded-xl border border-dashed border-border p-10 text-center text-xs text-muted-foreground bg-card/50"
            >
                Không có đơn Kho Tổng nào đang chờ bạn giao.
            </div>

            <div v-else class="grid grid-cols-1 gap-3.5 md:grid-cols-2">
                <Card
                    v-for="task in tasksByType('delivery')"
                    :key="task.id"
                    class="border-border/70 bg-card shadow-xs"
                >
                    <CardHeader class="pb-3">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <CardTitle class="flex items-center gap-2 text-sm font-semibold">
                                    <Truck class="size-4 text-primary" />
                                    <span>{{ task.supply_request?.request_code || `Task #${task.id}` }}</span>
                                </CardTitle>
                                <CardDescription class="mt-1 text-xs">
                                    Giao tới:
                                    <span class="font-medium text-foreground">{{ formatBranchName(task.supply_request?.to_branch) || 'Chi nhánh nhận hàng' }}</span>
                                </CardDescription>
                            </div>
                            <Badge
                                variant="outline"
                                class="text-[11px] font-medium"
                                :class="statusBadgeClass(task.status)"
                            >
                                {{ statusLabel(task.status) }}
                            </Badge>
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <p
                            v-if="task.notes"
                            class="rounded-lg bg-muted/40 p-3 text-xs leading-relaxed text-foreground"
                        >
                            {{ task.notes }}
                        </p>
                        <div
                            v-if="task.supply_request?.items?.length"
                            class="flex flex-wrap gap-1.5"
                        >
                            <span
                                v-for="item in task.supply_request.items"
                                :key="item.id"
                                class="rounded-md border border-border/70 bg-muted/20 px-2 py-1 text-[11px] text-muted-foreground"
                            >
                                {{ item.ingredient_name }} ·
                                <span class="font-medium text-foreground">{{ item.actual_dispatched_quantity ?? item.approved_quantity }}</span>
                                {{ item.unit || '' }}
                            </span>
                        </div>
                        <div class="pt-1">
                            <Button
                                v-if="task.status === 'assigned'"
                                class="w-full gap-1.5 text-xs font-medium"
                                :disabled="isProcessingTask"
                                @click="startTask(task)"
                            >
                                <ArrowRight class="size-3.5" /> Bắt đầu giao hàng
                            </Button>
                            <Button
                                v-if="task.status === 'in_progress'"
                                class="w-full gap-1.5 bg-emerald-600 text-xs font-medium text-white shadow-xs hover:bg-emerald-700 dark:bg-emerald-600 dark:hover:bg-emerald-700"
                                @click="openTaskCompletion(task)"
                            >
                                <CheckCircle class="size-4" />
                                Giao hàng thành công
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- 3. NHẬP NGOÀI VÀO KHO TỔNG -->
        <div v-if="activeTab === 'receiving'" class="flex flex-col gap-6">
            <Card
                v-if="!canManageWarehouse"
                class="border-border/70 shadow-xs"
            >
                <CardHeader>
                    <CardTitle class="text-base font-semibold text-foreground">
                        Phiếu nhập ngoài được phân công kiểm kê
                    </CardTitle>
                    <CardDescription class="text-xs text-muted-foreground">
                        Trưởng kho Tổng đã lập phiếu. Bạn phải kiểm đếm thực tế từng dòng và xác nhận; chỉ sau bước này nguyên liệu mới được cộng vào tồn Kho Tổng.
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div
                        v-if="assignedVerificationList.length === 0"
                        class="rounded-xl border border-dashed border-border p-8 text-center text-xs text-muted-foreground bg-muted/10"
                    >
                        Hiện không có phiếu nhập ngoài nào đang chờ bạn kiểm kê.
                    </div>
                    <div
                        v-for="voucher in assignedVerificationList"
                        :key="voucher.id"
                        class="rounded-xl border border-border/70 bg-muted/20 p-4 transition hover:border-border"
                    >
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="text-xs">
                                <p class="font-bold text-foreground font-mono">{{ voucher.voucher_code }}</p>
                                <p class="mt-1 text-muted-foreground">Nguồn ngoài: <span class="font-medium text-foreground">{{ voucher.external_source_name || 'Không ghi nhận' }}</span></p>
                                <p class="mt-1 text-muted-foreground">Người lập: {{ voucher.received_by?.name || '---' }} · {{ voucher.items?.length || 0 }} dòng · Khai báo: {{ formatQuantity(voucher.total_expected_qty) }}</p>
                            </div>
                            <Button
                                size="sm"
                                class="shrink-0 gap-1.5 text-xs font-medium"
                                @click="openVerification(voucher)"
                            >
                                <CheckCircle class="size-3.5" /> Kiểm kê & xác nhận
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card v-if="canManageWarehouse" class="border-border/70 shadow-xs">
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle
                                class="text-base font-semibold text-foreground"
                                >Tạo Phiếu Nhập Ngoài Vào Kho Tổng</CardTitle
                            >
                            <CardDescription class="text-xs text-muted-foreground"
                                >Ghi nhận nguyên liệu từ bên ngoài, không qua nhà cung cấp;
                                sau đó chờ Trưởng kho xác minh</CardDescription
                            >
                        </div>
                        <Button
                            type="button"
                            size="sm"
                            class="gap-1.5 text-xs font-medium"
                            @click="addGrnItem"
                        >
                            <Plus class="size-3.5" /> Thêm nguyên liệu
                        </Button>
                    </div>
                </CardHeader>
                <CardContent class="flex flex-col gap-5">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="flex flex-col gap-1.5">
                            <Label
                                class="text-xs font-medium text-foreground"
                                >Thời gian nhận hàng *</Label
                            >
                            <Input
                                type="datetime-local"
                                v-model="grnForm.received_at"
                                class="h-9 text-xs"
                            />
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <Label
                                class="text-xs font-medium text-foreground"
                                >Bên giao / nguồn bên ngoài *</Label
                            >
                            <Input
                                v-model="grnForm.external_source_name"
                                placeholder="Đơn vị tặng, đối tác hỗ trợ, người bàn giao..."
                                class="h-9 text-xs"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div class="rounded-xl border border-border/70 bg-muted/20 p-3.5">
                            <p class="text-[10px] font-semibold tracking-wide text-muted-foreground uppercase">Người lập</p>
                            <p class="mt-1 text-sm font-semibold text-foreground">{{ currentUser?.name || 'Tài khoản hiện tại' }}</p>
                            <p class="mt-1 text-[11px] text-muted-foreground">Được ghi tự động theo tài khoản lập phiếu.</p>
                        </div>
                        <div class="rounded-xl border border-border/70 bg-muted/20 p-3.5">
                            <p class="text-[10px] font-semibold tracking-wide text-muted-foreground uppercase">Phân công người kiểm kê *</p>
                            <select v-model="grnForm.verification_assigned_to" class="mt-1.5 h-9 w-full rounded-md border border-input bg-background px-2.5 text-xs shadow-xs text-foreground">
                                <option :value="null">Chọn nhân viên Kho Tổng</option>
                                <option v-for="staff in warehouseStaff" :key="staff.id" :value="staff.id">
                                    {{ staff.name }}{{ staff.job_title ? ` · ${staff.job_title}` : '' }}
                                </option>
                            </select>
                            <p class="mt-1 text-[11px] text-muted-foreground">Nhân viên được chọn sẽ kiểm đếm độc lập; phiếu chưa được cộng tồn kho trước khi họ xác nhận.</p>
                        </div>
                    </div>

                    <div
                        class="grid grid-cols-1 gap-3 rounded-xl border border-border/70 bg-muted/20 p-4 sm:grid-cols-2 lg:grid-cols-4"
                    >
                        <div class="flex flex-col gap-1">
                            <Label class="text-[11px] font-medium text-muted-foreground">Số tham chiếu bên ngoài</Label>
                            <Input
                                v-model="grnForm.external_reference"
                                placeholder="Biên bản / giấy tờ nếu có"
                                class="h-9 text-xs"
                            />
                        </div>
                        <div class="flex flex-col gap-1">
                            <Label class="text-[11px] font-medium text-muted-foreground">Lý do nhập ngoài *</Label>
                            <select
                                v-model="grnForm.external_receipt_reason"
                                class="h-9 rounded-md border border-input bg-background px-2 text-xs text-foreground"
                            >
                                <option value="external_donation">Biếu tặng / hỗ trợ từ bên ngoài</option>
                                <option value="external_return">Tiếp nhận hoàn từ bên ngoài</option>
                                <option value="other">Nhập ngoài khác</option>
                            </select>
                        </div>
                        <div class="flex flex-col gap-1">
                            <Label class="text-[11px] font-medium text-muted-foreground"
                                >Kết quả QC</Label
                            >
                            <select
                                v-model="grnForm.quality_status"
                                class="h-9 rounded-md border border-input bg-background px-2 text-xs text-foreground"
                            >
                                <option value="pending">Chờ QC</option>
                                <option value="passed">Đạt</option>
                                <option value="conditional">
                                    Đạt có điều kiện
                                </option>
                                <option value="failed">Không đạt</option>
                            </select>
                        </div>
                        <div class="flex flex-col gap-1 sm:col-span-2 lg:col-span-1">
                            <Label class="text-[11px] font-medium text-muted-foreground">Ghi chú QC</Label>
                            <Input
                                v-model="grnForm.quality_notes"
                                placeholder="Bao bì, ngoại quan..."
                                class="h-9 text-xs"
                            />
                        </div>
                    </div>

                    <div
                        v-if="grnForm.items.length === 0"
                        class="flex flex-col items-center justify-center rounded-xl border border-dashed border-border p-8 text-center bg-card/50"
                    >
                        <PackageOpen class="size-8 text-muted-foreground/60" />
                        <p
                            class="mt-2 text-xs font-medium text-muted-foreground"
                        >
                            Chưa có nguyên liệu nào trong phiếu nhận
                        </p>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            class="mt-3 gap-1.5 text-xs font-medium"
                            @click="addGrnItem"
                        >
                            <Plus class="size-3.5" /> Thêm nguyên liệu ngay
                        </Button>
                    </div>

                    <div v-else class="flex flex-col gap-3">
                        <div
                            v-for="(item, index) in grnForm.items"
                            :key="index"
                            class="rounded-xl border border-border/70 bg-card p-4 shadow-xs"
                        >
                            <div class="flex items-center justify-between pb-3">
                                <span
                                    class="text-xs font-bold text-foreground"
                                    >#{{ index + 1 }} Mặt hàng</span
                                >
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="size-7 p-0 text-rose-500 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-950/40"
                                    @click="removeGrnItem(index)"
                                >
                                    <Trash2 class="size-4" />
                                </Button>
                            </div>

                            <div
                                class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-6"
                            >
                                <div class="flex flex-col gap-1 sm:col-span-2">
                                    <Label
                                        class="text-[11px] font-medium text-muted-foreground"
                                        >Nguyên liệu *</Label
                                    >
                                    <select
                                        v-model="item.ingredient_id"
                                        @change="onGrnItemIngredientChange(item)"
                                        class="h-9 rounded-md border border-input bg-background px-2.5 text-xs shadow-xs text-foreground"
                                    >
                                        <option :value="null">
                                            -- Chọn nguyên liệu --
                                        </option>
                                        <option
                                            v-for="ing in ingredients"
                                            :key="ing.id"
                                            :value="ing.id"
                                        >
                                            {{ ing.name }} ({{ ing.sku }})
                                        </option>
                                    </select>
                                </div>

                                <div class="flex flex-col gap-1">
                                    <Label
                                        class="text-[11px] font-medium text-muted-foreground"
                                        >Đơn vị tính *</Label
                                    >
                                    <Input
                                        v-model="item.unit_label"
                                        placeholder="kg / túi / thùng"
                                        class="h-9 text-xs"
                                    />
                                    <span class="text-[10px] text-muted-foreground">Mặc định theo danh mục.</span>
                                </div>

                                <div class="flex flex-col gap-1">
                                    <Label
                                        class="text-[11px] font-medium text-muted-foreground"
                                        >Số lượng nhập *</Label
                                    >
                                    <Input
                                        type="number"
                                        v-model.number="item.actual_qty"
                                        step="0.001"
                                        class="h-9 text-xs"
                                        min="0.001"
                                    />
                                </div>

                                <div class="flex flex-col gap-1">
                                    <Label
                                        class="text-[11px] font-medium text-muted-foreground"
                                        >Đơn giá (đ) *</Label
                                    >
                                    <Input
                                        type="number"
                                        v-model.number="item.unit_cost"
                                        min="0"
                                        step="1"
                                        class="h-9 text-xs"
                                    />
                                </div>

                                <div class="flex flex-col gap-1">
                                    <Label
                                        class="text-[11px] font-medium text-muted-foreground"
                                        >Thành tiền (đ)</Label
                                    >
                                    <div class="flex h-9 items-center justify-end rounded-md border border-border/70 bg-muted/30 px-2.5 text-xs font-semibold text-foreground">
                                        {{ formatCurrency(grnLineTotal(item)) }}
                                    </div>
                                </div>

                                <div class="flex flex-col gap-1">
                                    <Label
                                        class="text-[11px] font-medium text-muted-foreground"
                                        >Mã lô (Lot Number) *</Label
                                    >
                                    <Input
                                        type="text"
                                        v-model="item.lot_number"
                                        placeholder="LOT-..."
                                        class="h-9 text-xs font-mono"
                                    />
                                </div>

                                <div class="flex flex-col gap-1">
                                    <Label
                                        class="text-[11px] font-medium text-muted-foreground"
                                        >Hạn sử dụng</Label
                                    >
                                    <Input
                                        type="date"
                                        v-model="item.expiry_date"
                                        class="h-9 text-xs"
                                    />
                                </div>

                                <div class="flex flex-col gap-1 sm:col-span-2 lg:col-span-2">
                                    <Label
                                        class="text-[11px] font-medium text-muted-foreground"
                                        >Vị trí cất hàng (nếu đã biết)</Label
                                    >
                                    <select
                                        v-model="item.location_id"
                                        class="h-9 rounded-md border border-input bg-background px-2.5 text-xs shadow-xs text-foreground"
                                    >
                                        <option :value="null">
                                            -- Chọn vị trí kho --
                                        </option>
                                        <option
                                            v-for="loc in locations"
                                            :key="loc.id"
                                            :value="loc.id"
                                        >
                                            {{ formatLocationName(loc) }}
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-3 rounded-xl border border-border/70 bg-muted/20 p-4 sm:grid-cols-2">
                            <div>
                                <p class="text-[11px] font-medium text-muted-foreground">Tổng số lượng nhập</p>
                                <p class="mt-1 text-lg font-bold text-foreground">
                                    {{ formatQuantity(grnForm.items.reduce((total, item) => total + Number(item.actual_qty || 0), 0)) }}
                                </p>
                            </div>
                            <div class="sm:text-right">
                                <p class="text-[11px] font-medium text-muted-foreground">Tổng hóa đơn / giá trị nhập ngoài</p>
                                <p class="mt-1 text-lg font-bold text-foreground">
                                    {{ formatCurrency(totalReceiptValue) }}
                                </p>
                                <p class="mt-1 text-[10px] text-muted-foreground">Dùng định giá tồn kho, không tạo công nợ nhà cung cấp.</p>
                            </div>
                        </div>

                        <!-- Attachments -->
                        <div class="flex flex-col gap-1.5 pt-2">
                            <Label
                                class="text-xs font-medium text-foreground"
                                >Biên bản / ảnh chứng minh nguồn nhập</Label
                            >
                            <div
                                class="flex flex-col items-center justify-center rounded-xl border border-dashed border-border p-4 transition-all hover:bg-muted/30"
                            >
                                <Upload class="size-5 text-muted-foreground" />
                                <span
                                    class="mt-1 text-xs font-medium text-muted-foreground"
                                    >Nhấn để chọn ảnh hoặc kéo thả chứng từ</span
                                >
                                <input
                                    type="file"
                                    multiple
                                    accept="image/*,application/pdf"
                                    class="mt-2 text-xs"
                                    @change="handleFileInput($event, 'grn')"
                                />
                            </div>
                            <div
                                v-if="grnFiles.length > 0"
                                class="flex flex-wrap gap-2 pt-2"
                            >
                                <span
                                    v-for="(f, i) in grnFiles"
                                    :key="f.name"
                                    class="flex items-center gap-1.5 rounded-full border border-border bg-muted/60 px-3 py-1 text-xs text-foreground"
                                >
                                    {{ f.name }}
                                    <button
                                        class="text-rose-500 hover:text-rose-600"
                                        @click="removeFile(i, 'grn')"
                                    >
                                        <X class="size-3" />
                                    </button>
                                </span>
                            </div>
                        </div>

                        <div class="flex justify-end pt-3">
                            <Button
                                class="gap-2 font-medium"
                                :disabled="isSubmittingGrn"
                                @click="submitGrn"
                            >
                                <PackageCheck class="size-4" />
                                {{
                                    isSubmittingGrn
                                        ? 'Đang tạo phiếu...'
                                        : 'Tạo Phiếu Nhập Ngoài'
                                }}
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Lịch sử phiếu nhập ngoài -->
            <div class="flex flex-col gap-3">
                <h3
                    class="text-xs font-semibold uppercase tracking-wider text-muted-foreground"
                >
                    Phiếu Nhập Ngoài Gần Đây Của Tôi
                </h3>
                <div
                    v-if="voucherList.length === 0"
                    class="rounded-xl border border-dashed border-border p-6 text-center text-xs text-muted-foreground bg-card/50"
                >
                    Chưa có phiếu nhập ngoài nào được tạo.
                </div>
                <div
                    v-else
                    class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3"
                >
                    <Card
                        v-for="voucher in voucherList"
                        :key="voucher.id"
                        class="border-border/70 bg-card shadow-xs transition hover:border-border"
                    >
                        <CardContent class="p-4">
                            <div class="flex items-center justify-between">
                                <span
                                    class="font-bold font-mono text-foreground"
                                    >{{ voucher.voucher_code }}</span
                                >
                                <Badge
                                    variant="outline"
                                    class="text-[11px] font-medium"
                                    :class="
                                        voucherStatusBadgeClass(voucher.status)
                                    "
                                >
                                    {{ voucherStatusLabel(voucher.status) }}
                                </Badge>
                            </div>
                            <p class="mt-2 text-xs text-muted-foreground">
                                {{ voucher.items?.length ?? 0 }} nguyên liệu •
                                Nhận:
                                {{
                                    new Date(
                                        voucher.received_at,
                                    ).toLocaleString('vi-VN')
                                }}
                            </p>
                            <div class="mt-2 grid grid-cols-2 gap-2 text-[11px]">
                                <p>
                                    <span class="text-muted-foreground">Người nhập:</span>
                                    <span class="font-medium text-foreground ml-1">{{ currentUser?.name || 'Tôi' }}</span>
                                </p>
                                <p>
                                    <span class="text-muted-foreground">Kiểm nhận:</span>
                                    <span class="font-medium text-foreground ml-1">{{ voucher.verified_by?.name || 'Chưa' }}</span>
                                </p>
                                <p class="col-span-2 font-semibold text-foreground">
                                    Tổng giá trị: {{ formatCurrency(voucherTotal(voucher)) }}
                                </p>
                            </div>
                            <div
                                v-if="
                                    (voucher.total_discrepancy_qty ?? 0) !== 0
                                "
                                class="mt-2 flex items-center gap-1 rounded-md bg-rose-500/10 px-2 py-1 text-xs font-medium text-rose-600 dark:text-rose-400 border border-rose-500/20"
                            >
                                <AlertTriangle class="size-3.5" /> Chênh lệch:
                                {{ voucher.total_discrepancy_qty }}
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>

        <!-- 3. CẤT HÀNG (PUTAWAY) -->
        <div v-if="activeTab === 'putaway'" class="flex flex-col gap-4">
            <div>
                <h2 class="text-base font-semibold text-foreground">
                    Cất Hàng Vào Vị Trí Lưu Trữ
                </h2>
                <p class="text-xs text-muted-foreground mt-0.5">
                    Đưa hàng hóa đã nhận vào đúng sơ đồ vị trí kệ kho đã quy định
                </p>
            </div>

            <div
                v-if="tasksByType('putaway').length === 0"
                class="rounded-xl border border-dashed border-border p-10 text-center text-xs text-muted-foreground bg-card/50"
            >
                <Box class="mx-auto size-7 text-muted-foreground/60 mb-2" />
                <p class="font-medium text-foreground">Không có tác vụ cất hàng</p>
                <p class="mt-1">Tất cả hàng hóa mới nhận đã được đưa vào đúng vị trí lưu kho.</p>
            </div>
            <div
                v-else
                class="grid grid-cols-1 gap-3.5 md:grid-cols-2 lg:grid-cols-3"
            >
                <Card
                    v-for="task in tasksByType('putaway')"
                    :key="task.id"
                    class="border-border/70 bg-card shadow-xs transition-colors hover:border-border"
                >
                    <CardContent class="p-4 space-y-3">
                        <div class="flex items-center justify-between">
                            <Badge
                                variant="outline"
                                class="border-border/80 bg-muted/40 font-medium text-foreground text-xs"
                            >Cất hàng</Badge>
                            <Badge
                                variant="outline"
                                :class="statusBadgeClass(task.status)"
                            >{{ statusLabel(task.status) }}</Badge>
                        </div>
                        <p
                            v-if="task.notes"
                            class="rounded-lg bg-muted/30 p-2.5 text-xs leading-relaxed text-muted-foreground"
                        >
                            {{ task.notes }}
                        </p>
                        <div
                            class="flex items-center justify-end gap-2 border-t border-border/60 pt-3"
                        >
                            <Button
                                v-if="task.status === 'assigned'"
                                size="sm"
                                class="gap-1.5 text-xs font-medium"
                                @click="startTask(task)"
                            >
                                <ArrowRight class="size-3.5" /> Bắt đầu cất hàng
                            </Button>
                            <Button
                                v-if="task.status === 'in_progress'"
                                size="sm"
                                class="gap-1.5 bg-emerald-600 text-xs font-medium text-white shadow-xs hover:bg-emerald-700 dark:bg-emerald-600 dark:hover:bg-emerald-700"
                                @click="openTaskCompletion(task)"
                            >
                                <CheckCircle class="size-3.5" /> {{ getTaskActionButtonLabel(task) }}
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- 4. SOẠN HÀNG (FEFO PICKING) -->
        <div v-if="activeTab === 'picking'" class="flex flex-col gap-4">
            <div
                class="flex items-start gap-3 rounded-xl border border-border/70 bg-muted/20 p-3.5"
            >
                <Shield
                    class="size-4 shrink-0 text-primary mt-0.5"
                />
                <div class="space-y-0.5">
                    <p class="text-xs font-semibold text-foreground">
                        Quy tắc xuất hàng FEFO (First Expired, First Out)
                    </p>
                    <p class="text-xs text-muted-foreground leading-relaxed">
                        Hệ thống tự động ưu tiên các lô có hạn dùng ngắn nhất. Nhân viên kho chỉ chọn lô khác khi có lý do giải trình đặc biệt.
                    </p>
                </div>
            </div>

            <div
                v-if="tasksByType('picking').length === 0"
                class="rounded-xl border border-dashed border-border p-10 text-center text-xs text-muted-foreground bg-card/50"
            >
                <ClipboardList class="mx-auto size-7 text-muted-foreground/60 mb-2" />
                <p class="font-medium text-foreground">Không có đơn soạn hàng</p>
                <p class="mt-1">Hiện tại không có đơn cấp phát chi nhánh nào cần soạn.</p>
            </div>

            <div
                v-else
                class="grid grid-cols-1 gap-3.5 md:grid-cols-2 lg:grid-cols-3"
            >
                <Card
                    v-for="task in tasksByType('picking')"
                    :key="task.id"
                    class="border-border/70 bg-card shadow-xs transition-colors hover:border-border"
                >
                    <CardContent class="p-4 space-y-3">
                        <div class="flex items-center justify-between">
                            <Badge
                                variant="outline"
                                class="border-border/80 bg-muted/40 font-medium text-foreground text-xs"
                            >Soạn hàng FEFO</Badge>
                            <Badge
                                variant="outline"
                                :class="statusBadgeClass(task.status)"
                            >{{ statusLabel(task.status) }}</Badge>
                        </div>
                        <div
                            v-if="task.supply_request"
                            class="rounded-lg bg-muted/30 p-2 text-xs font-medium text-foreground"
                        >
                            <span class="text-primary font-semibold">{{ task.supply_request.request_code }}</span>
                            <span class="text-muted-foreground mx-1.5">→</span>
                            <span>{{ formatBranchName(task.supply_request.to_branch) }}</span>
                        </div>
                        <p
                            v-if="task.notes"
                            class="rounded-lg bg-muted/30 p-2.5 text-xs leading-relaxed text-muted-foreground"
                        >
                            {{ task.notes }}
                        </p>
                        <div
                            class="flex items-center justify-end gap-2 border-t border-border/60 pt-3"
                        >
                            <Button
                                v-if="task.status === 'assigned'"
                                size="sm"
                                class="gap-1.5 text-xs font-medium"
                                @click="startTask(task)"
                            >
                                <ArrowRight class="size-3.5" /> Bắt đầu soạn
                            </Button>
                            <Button
                                v-if="task.status === 'in_progress'"
                                size="sm"
                                class="gap-1.5 bg-emerald-600 text-xs font-medium text-white shadow-xs hover:bg-emerald-700 dark:bg-emerald-600 dark:hover:bg-emerald-700"
                                @click="openTaskCompletion(task)"
                            >
                                <CheckCircle class="size-3.5" /> {{ getTaskActionButtonLabel(task) }}
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- 5. ĐÓNG GÓI (PACKING) -->
        <div v-if="activeTab === 'packing'" class="flex flex-col gap-4">
            <div>
                <h2 class="text-base font-semibold text-foreground">
                    Đóng Gói & Niêm Phong Kiện Hàng
                </h2>
                <p class="text-xs text-muted-foreground mt-0.5">
                    Đóng gói quy cách và gắn mã seal trước khi bàn giao cho xe vận chuyển
                </p>
            </div>

            <div
                v-if="tasksByType('packing').length === 0"
                class="rounded-xl border border-dashed border-border p-10 text-center text-xs text-muted-foreground bg-card/50"
            >
                <Package class="mx-auto size-7 text-muted-foreground/60 mb-2" />
                <p class="font-medium text-foreground">Không có kiện hàng cần đóng gói</p>
                <p class="mt-1">Mọi đơn hàng đã được niêm phong và chuyển cho bộ phận vận chuyển.</p>
            </div>
            <div
                v-else
                class="grid grid-cols-1 gap-3.5 md:grid-cols-2 lg:grid-cols-3"
            >
                <Card
                    v-for="task in tasksByType('packing')"
                    :key="task.id"
                    class="border-border/70 bg-card shadow-xs transition-colors hover:border-border"
                >
                    <CardContent class="p-4 space-y-3">
                        <div class="flex items-center justify-between">
                            <Badge
                                variant="outline"
                                class="border-border/80 bg-muted/40 font-medium text-foreground text-xs"
                            >Đóng gói</Badge>
                            <Badge
                                variant="outline"
                                :class="statusBadgeClass(task.status)"
                            >{{ statusLabel(task.status) }}</Badge>
                        </div>
                        <p
                            v-if="task.notes"
                            class="rounded-lg bg-muted/30 p-2.5 text-xs leading-relaxed text-muted-foreground"
                        >
                            {{ task.notes }}
                        </p>
                        <div
                            class="flex items-center justify-end gap-2 border-t border-border/60 pt-3"
                        >
                            <Button
                                v-if="task.status === 'assigned'"
                                size="sm"
                                class="gap-1.5 text-xs font-medium"
                                @click="startTask(task)"
                            >
                                <ArrowRight class="size-3.5" /> Bắt đầu đóng gói
                            </Button>
                            <Button
                                v-if="task.status === 'in_progress'"
                                size="sm"
                                class="gap-1.5 bg-emerald-600 text-xs font-medium text-white shadow-xs hover:bg-emerald-700 dark:bg-emerald-600 dark:hover:bg-emerald-700"
                                @click="openTaskCompletion(task)"
                            >
                                <CheckCircle class="size-3.5" /> {{ getTaskActionButtonLabel(task) }}
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- 6. KIỂM KÊ (COUNTING) -->
        <div v-if="activeTab === 'counting'" class="flex flex-col gap-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold text-foreground">
                        Kiểm Kê Tồn Kho Theo Phiên
                    </h2>
                    <p class="text-xs text-muted-foreground mt-0.5">
                        Kiểm đếm thực tế tồn kho theo phân công của Trưởng kho
                    </p>
                </div>
                <Link href="/inventory/count-sessions">
                    <Button variant="outline" size="sm" class="gap-2 text-xs">
                        Mở phiên kiểm kê chính thức
                        <ArrowRight class="size-3.5" />
                    </Button>
                </Link>
            </div>

            <div
                v-if="tasksByType('counting').length === 0"
                class="rounded-xl border border-dashed border-border p-10 text-center text-xs text-muted-foreground bg-card/50"
            >
                <BadgeCheck class="mx-auto size-7 text-muted-foreground/60 mb-2" />
                <p class="font-medium text-foreground">Không có task kiểm kê phân công</p>
                <p class="mt-1">Bạn có thể truy cập trang Kiểm Kê Tồn Kho để xem các phiên kiểm kê toàn kho.</p>
            </div>
            <div
                v-else
                class="grid grid-cols-1 gap-3.5 md:grid-cols-2 lg:grid-cols-3"
            >
                <Card
                    v-for="task in tasksByType('counting')"
                    :key="task.id"
                    class="border-border/70 bg-card shadow-xs transition-colors hover:border-border"
                >
                    <CardContent class="p-4 space-y-3">
                        <div class="flex items-center justify-between">
                            <Badge
                                variant="outline"
                                class="border-border/80 bg-muted/40 font-medium text-foreground text-xs"
                            >Kiểm kê</Badge>
                            <Badge
                                variant="outline"
                                :class="statusBadgeClass(task.status)"
                            >{{ statusLabel(task.status) }}</Badge>
                        </div>
                        <div
                            v-if="task.count_session"
                            class="rounded-lg border border-border/70 bg-muted/20 p-2.5 text-xs text-foreground"
                        >
                            <div class="font-semibold">
                                Chốt nguyên liệu #{{ task.count_session.id }}
                            </div>
                            <div class="mt-0.5 text-muted-foreground">
                                {{ task.count_session.period_start }} →
                                {{ task.count_session.period_end }}
                            </div>
                        </div>
                        <p
                            v-if="task.notes"
                            class="rounded-lg bg-muted/30 p-2.5 text-xs leading-relaxed text-muted-foreground"
                        >
                            {{ task.notes }}
                        </p>
                        <div
                            class="flex items-center justify-end gap-2 border-t border-border/60 pt-3"
                        >
                            <Button
                                v-if="task.status === 'assigned'"
                                size="sm"
                                class="gap-1.5 text-xs font-medium"
                                @click="startTask(task.id)"
                            >
                                <ArrowRight class="size-3.5" /> Bắt đầu đếm
                            </Button>
                            <Link
                                v-if="task.count_session"
                                :href="`/inventory/central-warehouse/material-closing?session=${task.count_session.id}`"
                            >
                                <Button
                                    size="sm"
                                    variant="outline"
                                    class="gap-1.5 text-xs font-medium"
                                >
                                    <ClipboardList class="size-3.5" /> Mở kỳ chốt
                                </Button>
                            </Link>
                            <Button
                                v-if="
                                    task.status === 'in_progress' &&
                                    !task.count_session
                                "
                                size="sm"
                                class="gap-1.5 bg-emerald-600 text-xs font-medium text-white shadow-xs hover:bg-emerald-700 dark:bg-emerald-600 dark:hover:bg-emerald-700"
                                @click="openTaskCompletion(task)"
                            >
                                <CheckCircle class="size-3.5" /> Nộp kết quả
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- 7. BÁO CÁO SỰ CỐ (INCIDENT) -->
        <div v-if="activeTab === 'incident'" class="flex flex-col gap-6">
            <Card
                v-if="warehouseReceivingReportList.length"
                class="border-border/70 shadow-xs"
            >
                <CardHeader>
                    <CardTitle class="text-base font-semibold text-foreground flex items-center gap-2">
                        <AlertTriangle class="size-4 text-rose-500" />
                        Phiếu xác nhận nguyên liệu vào Kho Tổng
                    </CardTitle>
                    <CardDescription class="text-xs text-muted-foreground">
                        Biên bản kiểm kê được tự động lập khi số lượng hoặc chất lượng có vấn đề theo mẫu xác nhận nguyên liệu vào Kho Tổng. Nhân viên kiểm kê đã xác nhận; Chủ doanh nghiệp và người lập phiếu đã được gửi thông báo.
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div
                        v-for="report in warehouseReceivingReportList"
                        :key="report.id"
                        :class="[
                            'rounded-xl border p-4 transition-colors',
                            report.id === highlightedWarehouseReportId
                                ? 'border-primary/50 bg-primary/5 ring-1 ring-primary/20'
                                : 'border-border/70 bg-card',
                        ]"
                    >
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <div class="text-xs">
                                <div class="font-semibold text-foreground">
                                    {{ report.report_code }} · Phiếu {{ report.voucher?.voucher_code || '---' }}
                                </div>
                                <div class="mt-1 text-muted-foreground">
                                    Nguồn: {{ report.voucher?.external_source_name || '---' }} · Người lập: {{ report.voucher?.received_by?.name || '---' }}
                                </div>
                                <div class="mt-1 text-muted-foreground/80">
                                    Nhân viên xác nhận: {{ report.employee_confirmed_by?.name || report.submitted_by?.name || '---' }} ·
                                    {{ report.employee_confirmed_at ? new Date(report.employee_confirmed_at).toLocaleString('vi-VN') : '---' }}
                                </div>
                            </div>
                            <Badge variant="outline" class="shrink-0 border-rose-500/20 bg-rose-500/10 text-rose-600 dark:text-rose-400 font-medium text-xs">
                                Đã xác nhận · {{ report.issue_type === 'quality_issue' ? 'Vấn đề chất lượng' : report.issue_type === 'quantity_and_quality' ? 'Lệch số lượng & chất lượng' : 'Lệch số lượng' }}
                            </Badge>
                        </div>
                        <p class="mt-3 rounded-lg border border-border/50 bg-muted/30 p-3 text-xs leading-relaxed text-foreground">
                            {{ report.issue_summary }}
                        </p>
                        <div class="mt-3 overflow-x-auto rounded-lg border border-border/60">
                            <table class="w-full min-w-[620px] text-xs">
                                <thead class="bg-muted/50 text-muted-foreground font-medium">
                                    <tr>
                                        <th class="p-2.5 text-left">Nguyên liệu / lô</th>
                                        <th class="p-2.5 text-right">Theo chứng từ</th>
                                        <th class="p-2.5 text-right">Thực nhận</th>
                                        <th class="p-2.5 text-right">Chênh lệch</th>
                                        <th class="p-2.5 text-right">Thành tiền</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border/60">
                                    <tr v-for="item in report.items || []" :key="item.id">
                                        <td class="p-2.5">
                                            <div class="font-medium text-foreground">{{ item.ingredient_name_snapshot }}</div>
                                            <div class="mt-0.5 text-[11px] text-muted-foreground">{{ item.unit_symbol_snapshot || 'đv' }} · Lô {{ item.lot_number || '---' }}</div>
                                        </td>
                                        <td class="p-2.5 text-right font-mono text-muted-foreground">{{ formatQuantity(item.expected_quantity) }}</td>
                                        <td class="p-2.5 text-right font-mono font-medium text-foreground">{{ formatQuantity(item.actual_quantity) }}</td>
                                        <td :class="['p-2.5 text-right font-mono font-semibold', Number(item.difference_quantity) < 0 ? 'text-rose-600 dark:text-rose-400' : Number(item.difference_quantity) > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-muted-foreground']">
                                            {{ Number(item.difference_quantity) > 0 ? '+' : '' }}{{ formatQuantity(item.difference_quantity) }}
                                        </td>
                                        <td class="p-2.5 text-right font-mono font-medium text-foreground">{{ formatCurrency(item.line_value) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card
                v-if="receivingReportList.length"
                class="border-border/70 shadow-xs"
            >
                <CardHeader>
                    <CardTitle class="text-base font-semibold text-foreground flex items-center gap-2">
                        <AlertTriangle class="size-4 text-amber-500" />
                        Biên bản nhận hàng cần xác nhận
                    </CardTitle>
                    <CardDescription class="text-xs text-muted-foreground">
                        Kiểm tra lại các nguyên liệu thiếu/hỏng/hết hạn/sai hàng rồi xác nhận để Trưởng Kho Tổng xử lý.
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div
                        v-for="report in receivingReportList"
                        :key="report.id"
                        class="rounded-xl border border-border/70 bg-card p-4"
                    >
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="text-xs">
                                <div class="font-semibold text-foreground">
                                    {{ report.report_code }} · Đơn {{ report.supply_request?.request_code }}
                                </div>
                                <div class="mt-1 text-muted-foreground">
                                    Chi nhánh: {{ report.supply_request?.to_branch?.name || '---' }}
                                </div>
                                <div class="mt-2 space-y-1 text-muted-foreground">
                                    <div v-for="item in (report.items || []).filter((row: any) => Number(row.submitted_damaged_quantity || 0) + Number(row.submitted_expired_quantity || 0) + Number(row.submitted_wrong_item_quantity || 0) + Number(row.submitted_shortage_quantity || 0) > 0)" :key="item.id">
                                        <span class="font-medium text-foreground">{{ item.ingredient?.name || item.ingredient_name_snapshot }}</span>:
                                        đạt {{ item.submitted_good_quantity }}, hỏng {{ item.submitted_damaged_quantity }}, hết hạn {{ item.submitted_expired_quantity }}, thiếu {{ item.submitted_shortage_quantity }}
                                    </div>
                                </div>
                            </div>
                            <Button
                                v-if="report.status === 'confirmed_pending_ack'"
                                size="sm"
                                class="shrink-0 gap-1.5 text-xs font-medium"
                                @click="confirmReceivingReport(report)"
                            >
                                <CheckCircle class="size-3.5" /> Xác nhận biên bản
                            </Button>
                            <Badge v-else variant="outline" class="shrink-0 border-emerald-500/20 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 font-medium text-xs">
                                Đã xác nhận
                            </Badge>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card
                v-if="disputeList.length"
                class="border-border/70 shadow-xs"
            >
                <CardHeader>
                    <CardTitle
                        class="text-base font-semibold text-foreground flex items-center gap-2"
                    >
                        <AlertTriangle class="size-4 text-rose-500" />
                        Biên bản tranh chấp cần phản hồi
                    </CardTitle>
                    <CardDescription class="text-xs text-muted-foreground"
                        >Các biên bản được quy trách nhiệm cho tài khoản của bạn. Phản hồi sẽ được ghi vào audit trail để Trưởng kho xem xét.</CardDescription
                    >
                </CardHeader>
                <CardContent class="space-y-3">
                    <div
                        v-for="dispute in disputeList"
                        :key="dispute.id"
                        class="flex flex-col gap-3 rounded-xl border border-border/70 bg-card p-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="text-xs">
                            <div class="font-semibold text-foreground">
                                {{ dispute.dispute_code }} · {{ dispute.ingredient?.name }}
                            </div>
                            <div class="mt-1 text-muted-foreground">
                                Thiếu {{ dispute.discrepancy_quantity }} · Thiệt hại {{ dispute.financial_loss_amount }}
                            </div>
                        </div>
                        <Button
                            size="sm"
                            variant="outline"
                            class="text-xs font-medium border-rose-500/20 text-rose-600 hover:bg-rose-500/10"
                            @click="respondToDispute(dispute)"
                        >
                            Gửi phản hồi
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <Card class="border-border/70 shadow-xs">
                <CardHeader>
                    <CardTitle
                        class="text-base font-semibold text-foreground"
                    >
                        Báo Cáo Sự Cố Kho & Chất Lượng
                    </CardTitle>
                    <CardDescription class="text-xs text-muted-foreground">
                        Phản ánh kịp thời hàng hư hại, ẩm mốc, hết hạn hoặc sai lệch số lượng để Trưởng kho xử lý
                    </CardDescription>
                </CardHeader>
                <CardContent class="flex flex-col gap-4">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div class="flex flex-col gap-1.5">
                            <Label class="text-xs font-medium text-foreground">Loại sự cố *</Label>
                            <select
                                v-model="incidentForm.incident_type"
                                class="h-9 rounded-md border border-input bg-background px-3 text-xs shadow-xs focus:ring-1 focus:ring-ring text-foreground"
                            >
                                <option value="shortage">Thiếu hàng / Hao hụt</option>
                                <option value="damage">Hàng hỏng / Ẩm mốc / Rách bao bì</option>
                                <option value="expired">Hàng cận hoặc quá hạn sử dụng</option>
                                <option value="wrong_item">Nhầm mã hàng / Sai thông số</option>
                                <option value="other">Sự cố khác</option>
                            </select>
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <Label class="text-xs font-medium text-foreground">Nguyên liệu liên quan</Label>
                            <select
                                v-model="incidentForm.ingredient_id"
                                class="h-9 rounded-md border border-input bg-background px-3 text-xs shadow-xs focus:ring-1 focus:ring-ring text-foreground"
                            >
                                <option :value="null">-- Không xác định / Toàn bộ --</option>
                                <option
                                    v-for="ing in ingredients"
                                    :key="ing.id"
                                    :value="ing.id"
                                >
                                    {{ ing.name }}
                                </option>
                            </select>
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <Label class="text-xs font-medium text-foreground">Số lượng ảnh hưởng</Label>
                            <Input
                                type="number"
                                v-model.number="incidentForm.quantity_affected"
                                min="0"
                                step="0.001"
                                placeholder="0"
                                class="h-9 text-xs"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="flex flex-col gap-1.5">
                            <Label class="text-xs font-medium text-foreground">Mã Lô / Batch ID (nếu có)</Label>
                            <Input
                                v-model.number="incidentForm.batch_id"
                                type="number"
                                placeholder="Nhập ID batch/lô hàng..."
                                class="h-9 text-xs"
                            />
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <Label class="text-xs font-medium text-foreground">Vị trí phát hiện (nếu có)</Label>
                            <select
                                v-model="incidentForm.location_id"
                                class="h-9 rounded-md border border-input bg-background px-3 text-xs shadow-xs focus:ring-1 focus:ring-ring text-foreground"
                            >
                                <option :value="null">-- Chọn vị trí phát hiện --</option>
                                <option
                                    v-for="loc in locations"
                                    :key="loc.id"
                                    :value="loc.id"
                                >
                                    {{ formatLocationName(loc) }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <Label class="text-xs font-medium text-foreground">Mô tả chi tiết sự cố *</Label>
                        <textarea
                            v-model="incidentForm.description"
                            rows="4"
                            placeholder="Mô tả cụ thể vị trí kệ hàng, thời điểm phát hiện, nguyên nhân sơ bộ..."
                            class="rounded-md border border-input bg-background p-3 text-xs shadow-xs focus:ring-1 focus:ring-ring text-foreground placeholder:text-muted-foreground"
                        ></textarea>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <Label class="text-xs font-medium text-foreground">Ảnh hiện trường chứng minh</Label>
                        <div
                            class="flex flex-col items-center justify-center rounded-xl border border-dashed border-border bg-muted/20 p-5 transition-colors hover:bg-muted/30"
                        >
                            <Upload class="size-5 text-muted-foreground" />
                            <span class="mt-1 text-xs font-medium text-foreground">Chụp ảnh hoặc chọn file từ máy</span>
                            <input
                                type="file"
                                multiple
                                accept="image/*"
                                class="mt-2 text-xs"
                                @change="handleFileInput($event, 'incident')"
                            />
                        </div>
                        <div
                            v-if="incidentFiles.length > 0"
                            class="flex flex-wrap gap-2 pt-2"
                        >
                            <span
                                v-for="(f, i) in incidentFiles"
                                :key="f.name"
                                class="flex items-center gap-1.5 rounded-md border border-border/70 bg-muted/30 px-2.5 py-1 text-xs text-foreground"
                            >
                                {{ f.name }}
                                <button
                                    class="text-muted-foreground hover:text-rose-500"
                                    @click="removeFile(i, 'incident')"
                                >
                                    <X class="size-3" />
                                </button>
                            </span>
                        </div>
                    </div>

                    <div class="flex justify-end pt-2">
                        <Button
                            class="gap-2 text-xs font-medium bg-rose-600 hover:bg-rose-700 text-white shadow-xs"
                            :disabled="isSubmittingIncident"
                            @click="submitIncident"
                        >
                            <AlertTriangle class="size-4" />
                            {{
                                isSubmittingIncident
                                    ? 'Đang gửi...'
                                    : 'Gửi Báo Cáo Sự Cố'
                            }}
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- 8. BÀN GIAO CA (HANDOVER) -->
        <div v-if="activeTab === 'handover'" class="flex flex-col gap-6">
            <!-- Alert if tasks remain -->
            <div
                v-if="
                    taskSummaryData.pending > 0 ||
                    taskSummaryData.in_progress > 0
                "
                class="flex items-center gap-3 rounded-xl border border-amber-500/30 bg-amber-500/10 p-3.5 text-xs text-foreground"
            >
                <AlertTriangle class="size-4 text-amber-500 shrink-0" />
                <div>
                    <span class="font-semibold">Lưu ý bàn giao:</span> Bạn vẫn còn
                    <span class="font-semibold underline"
                        >{{
                            taskSummaryData.pending +
                            taskSummaryData.in_progress
                        }}
                        tác vụ</span
                    >
                    chưa hoàn tất. Hãy xử lý hoặc ghi chú rõ trong biên bản bàn giao.
                </div>
            </div>

            <Card class="border-border/70 shadow-xs">
                <CardHeader>
                    <CardTitle
                        class="text-base font-semibold text-foreground"
                    >
                        Biên Bản Bàn Giao Cuối Ca
                    </CardTitle>
                    <CardDescription class="text-xs text-muted-foreground">
                        Chốt trạng thái vệ sinh kho, an toàn thiết bị, hàng tồn và bàn giao cho ca kế tiếp
                    </CardDescription>
                </CardHeader>
                <CardContent class="flex flex-col gap-4">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div class="flex flex-col gap-1.5">
                            <Label class="text-xs font-medium text-foreground">Ngày làm việc *</Label>
                            <Input
                                type="date"
                                v-model="handoverForm.shift_date"
                                class="h-9 text-xs"
                            />
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <Label class="text-xs font-medium text-foreground">Ca làm việc *</Label>
                            <select
                                v-model="handoverForm.shift_type"
                                class="h-9 rounded-md border border-input bg-background px-3 text-xs shadow-xs focus:ring-1 focus:ring-ring text-foreground"
                            >
                                <option value="morning">Ca Sáng (06:00 - 14:00)</option>
                                <option value="afternoon">Ca Chiều (14:00 - 22:00)</option>
                                <option value="evening">Ca Tối (18:00 - 23:00)</option>
                                <option value="night">Ca Đêm (22:00 - 06:00)</option>
                            </select>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <Label class="text-xs font-medium text-foreground">Nhãn ca làm việc</Label>
                            <Input
                                type="text"
                                v-model="handoverForm.shift_label"
                                placeholder="VD: Ca sáng Kho Tổng A"
                                class="h-9 text-xs"
                            />
                        </div>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <Label class="text-xs font-medium text-foreground">Nội dung ghi chú bàn giao</Label>
                        <textarea
                            v-model="handoverForm.notes"
                            rows="3"
                            placeholder="Tình trạng kho bãi, hàng hóa cần kiểm tra đặc biệt, thiết bị xe nâng/kho lạnh..."
                            class="rounded-md border border-input bg-background p-3 text-xs shadow-xs focus:ring-1 focus:ring-ring text-foreground placeholder:text-muted-foreground"
                        ></textarea>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <Label class="text-xs font-medium text-foreground">Người nhận ca *</Label>
                        <select
                            v-model="handoverForm.received_by"
                            class="h-9 rounded-md border border-input bg-background px-3 text-xs shadow-xs focus:ring-1 focus:ring-ring text-foreground"
                        >
                            <option :value="0">Chọn nhân sự nhận ca</option>
                            <option
                                v-for="recipient in handoverRecipients"
                                :key="recipient.id"
                                :value="recipient.id"
                            >
                                {{ recipient.name }}
                            </option>
                        </select>
                        <p class="text-[11px] text-muted-foreground">
                            Biên bản sẽ chờ người nhận xác nhận và hệ thống sẽ gửi thông báo.
                        </p>
                    </div>

                    <div class="flex justify-end pt-2">
                        <Button
                            class="gap-2 text-xs font-medium"
                            :disabled="isSubmittingHandover"
                            @click="submitHandover"
                        >
                            <Truck class="size-4" />
                            {{
                                isSubmittingHandover
                                    ? 'Đang gửi...'
                                    : 'Nộp Biên Bản Bàn Giao'
                            }}
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <div class="flex flex-col gap-3">
                <h3
                    class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                >
                    Lịch Sử Bàn Giao Gần Đây
                </h3>
                <div
                    v-if="handoverList.length === 0"
                    class="rounded-xl border border-dashed border-border p-8 text-center text-xs text-muted-foreground bg-card/50"
                >
                    Chưa có dữ liệu bàn giao ca trước đó.
                </div>
                <div
                    v-else
                    class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3"
                >
                    <Card
                        v-for="handover in handoverList"
                        :key="handover.id"
                        class="border-border/70 bg-card shadow-xs transition-colors hover:border-border"
                    >
                        <CardContent class="p-4">
                            <div class="flex items-center justify-between">
                                <span
                                    class="font-semibold text-foreground text-xs"
                                >
                                    {{ handover.shift_date }} — {{ handover.shift_label || handover.shift_type }}
                                </span>
                                <Badge
                                    variant="outline"
                                    class="font-medium text-xs"
                                    :class="
                                        handover.status === 'confirmed'
                                            ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                                            : 'border-amber-500/20 bg-amber-500/10 text-amber-600 dark:text-amber-400'
                                    "
                                >
                                    {{
                                        handover.status === 'confirmed'
                                            ? 'Đã xác nhận'
                                            : 'Chờ xác nhận'
                                    }}
                                </Badge>
                            </div>
                            <p class="mt-2 text-xs text-muted-foreground">
                                Tạo lúc:
                                {{
                                    new Date(
                                        handover.created_at,
                                    ).toLocaleString('vi-VN')
                                }}
                            </p>
                            <div
                                v-if="handover.is_system_locked"
                                class="mt-2 flex items-center gap-1 text-xs font-medium text-rose-600 dark:text-rose-400"
                            >
                                <AlertTriangle class="size-3.5" />
                                {{ handover.lock_reason }}
                            </div>
                            <Button
                                v-if="
                                    handover.status === 'pending' &&
                                    handover.received_by === currentUser?.id
                                "
                                size="sm"
                                class="mt-3 w-full bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-medium"
                                @click="confirmHandover(handover.id)"
                            >
                                Xác nhận đã nhận bàn giao
                            </Button>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>

        <!-- ── Modal: Kiểm kê độc lập trước khi nhập tồn ── -->
        <Teleport to="body">
            <div
                v-if="activeVerificationVoucher"
                class="fixed inset-0 z-50 flex items-center justify-center bg-background/80 p-4 backdrop-blur-sm"
                @click.self="closeVerification"
            >
                <div class="max-h-[92vh] w-full max-w-3xl overflow-y-auto rounded-2xl border border-border/70 bg-card p-5 shadow-xl">
                    <div class="flex items-start justify-between gap-3 border-b border-border/60 pb-4">
                        <div>
                            <p class="text-[11px] font-semibold tracking-wider text-primary uppercase">Kiểm kê độc lập trước khi nhập tồn</p>
                            <h3 class="mt-1 text-base font-semibold text-foreground">{{ activeVerificationVoucher.voucher_code }}</h3>
                            <p class="mt-1 text-xs text-muted-foreground">Người lập: {{ activeVerificationVoucher.received_by?.name || '---' }} · Nguồn ngoài: {{ activeVerificationVoucher.external_source_name || '---' }}</p>
                        </div>
                        <button type="button" class="text-muted-foreground hover:text-foreground" @click="closeVerification">
                            <X class="size-5" />
                        </button>
                    </div>

                    <div class="mt-4 overflow-x-auto rounded-xl border border-border/60">
                        <table class="w-full min-w-[720px] text-xs">
                            <thead class="bg-muted/50 text-muted-foreground font-medium">
                                <tr>
                                    <th class="p-3 text-left">Nguyên liệu / lô</th>
                                    <th class="p-3 text-right">SL Trưởng kho khai báo</th>
                                    <th class="p-3 text-left">Đơn vị</th>
                                    <th class="p-3 text-right">SL kiểm kê thực tế *</th>
                                    <th class="p-3 text-right">Đơn giá</th>
                                    <th class="p-3 text-right">Thành tiền</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border/60">
                                <tr v-for="(item, index) in (activeVerificationVoucher.items || [])" :key="item.id">
                                    <td class="p-3">
                                        <p class="font-medium text-foreground">{{ item.ingredient?.name || 'Nguyên liệu' }}</p>
                                        <p class="mt-0.5 font-mono text-[11px] text-muted-foreground">Lô: {{ item.lot_number || '---' }}</p>
                                    </td>
                                    <td class="p-3 text-right font-mono text-muted-foreground">{{ formatQuantity(item.expected_qty) }}</td>
                                    <td class="p-3 text-muted-foreground">{{ item.unit_label || item.ingredient?.unit?.symbol || 'đv' }}</td>
                                    <td class="p-3">
                                        <Input
                                            v-model.number="verificationItems[Number(index)].actual_qty"
                                            type="number"
                                            min="0.001"
                                            step="0.001"
                                            class="h-8 text-right font-mono text-xs font-semibold"
                                        />
                                    </td>
                                    <td class="p-3 text-right font-mono text-muted-foreground">{{ formatCurrency(item.unit_cost) }}</td>
                                    <td class="p-3 text-right font-mono font-medium text-foreground">{{ formatCurrency(Number(verificationItems[Number(index)]?.actual_qty || 0) * Number(item.unit_cost || 0)) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <div class="flex flex-col gap-1.5">
                            <Label class="text-xs font-medium text-foreground">Kết quả kiểm tra chất lượng *</Label>
                            <select v-model="verificationQualityStatus" class="h-9 rounded-md border border-input bg-background px-3 text-xs shadow-xs focus:ring-1 focus:ring-ring text-foreground">
                                <option value="passed">Đạt</option>
                                <option value="conditional">Đạt có điều kiện</option>
                                <option value="failed">Không đạt</option>
                            </select>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <Label class="text-xs font-medium text-foreground">Ghi chú chất lượng</Label>
                            <Input v-model="verificationQualityNotes" class="h-9 text-xs" placeholder="Ngoại quan, bao bì, điều kiện bảo quản..." />
                        </div>
                    </div>
                    <div class="mt-3 flex flex-col gap-1.5">
                        <Label class="text-xs font-medium text-foreground">Biên bản kiểm kê / giải trình chênh lệch</Label>
                        <textarea v-model="verificationNotes" rows="3" class="rounded-md border border-input bg-background px-3 py-2 text-xs shadow-xs focus:ring-1 focus:ring-ring text-foreground placeholder:text-muted-foreground" placeholder="Ghi rõ kết quả đếm thực tế; bắt buộc nếu lệch số Trưởng kho khai báo." />
                        <p v-if="verificationHasIssue()" class="text-[11px] font-medium text-rose-600 dark:text-rose-400">
                            Có phát sinh chênh lệch hoặc vấn đề chất lượng. Khi xác nhận, hệ thống sẽ lập biên bản và gửi ngay cho Chủ doanh nghiệp cùng người lập phiếu.
                        </p>
                    </div>

                    <div class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-border/60 pt-4">
                        <p class="max-w-xl text-[11px] text-muted-foreground">Xác nhận này là căn cứ để hạch toán số lượng thực tế vào tồn Kho Tổng. Nếu có vấn đề, biên bản kiểm kê sẽ được lưu cùng phiếu và gửi cho các bên liên quan.</p>
                        <div class="flex gap-2">
                            <Button type="button" variant="outline" size="sm" @click="closeVerification">Hủy</Button>
                            <Button type="button" size="sm" class="gap-1.5 text-xs font-medium" :disabled="isSubmittingVerification" @click="submitVerification">
                                <CheckCircle class="size-3.5" /> {{ isSubmittingVerification ? 'Đang xác nhận...' : verificationHasIssue() ? 'Lập biên bản & xác nhận' : 'Xác nhận kiểm kê & nhập tồn' }}
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ── Modal: Xác Nhận Hoàn Thành Tác Vụ ── -->
        <Teleport to="body">
            <div
                v-if="activeTaskId !== null"
                class="fixed inset-0 z-50 flex items-center justify-center bg-background/80 p-4 backdrop-blur-sm"
                @click.self="activeTaskId = null"
            >
                <div
                    class="w-full max-w-lg rounded-2xl border border-border/70 bg-card p-6 shadow-xl"
                >
                    <div
                        class="flex items-center justify-between border-b border-border/60 pb-4"
                    >
                        <h3
                            class="text-base font-semibold text-foreground"
                        >
                            Xác Nhận Hoàn Thành Tác Vụ
                        </h3>
                        <button
                            class="text-muted-foreground hover:text-foreground"
                            @click="activeTaskId = null"
                        >
                            <X class="size-5" />
                        </button>
                    </div>
                    <div class="flex flex-col gap-4 py-4">
                        <div class="flex flex-col gap-1.5">
                            <Label
                                class="text-xs font-medium text-foreground"
                                >Ghi chú kết quả thực tế</Label
                            >
                            <textarea
                                v-model="taskResultNote"
                                rows="3"
                                placeholder="Mô tả kết quả thực hiện, số lượng thực tế, vị trí đã cất..."
                                class="rounded-md border border-input bg-background p-3 text-xs shadow-xs focus:ring-1 focus:ring-ring text-foreground placeholder:text-muted-foreground"
                            ></textarea>
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <Label
                                class="text-xs font-medium text-foreground"
                                >Ảnh bằng chứng hoàn thành (tuỳ chọn)</Label
                            >
                            <label
                                class="group flex cursor-pointer flex-col items-center justify-center rounded-xl border border-dashed border-border bg-muted/20 p-5 text-center transition hover:border-primary/50 hover:bg-muted/30"
                            >
                                <input
                                    type="file"
                                    multiple
                                    accept="image/*,application/pdf"
                                    class="sr-only"
                                    @change="handleFileInput($event, 'task')"
                                />
                                <div
                                    class="flex size-9 items-center justify-center rounded-lg bg-primary/10 text-primary transition group-hover:scale-105"
                                >
                                    <Upload class="size-4" />
                                </div>
                                <span
                                    class="mt-2 text-xs font-medium text-foreground"
                                >
                                    Nhấn vào đây để tải ảnh chứng từ / hàng hóa
                                </span>
                                <span
                                    class="mt-0.5 text-[11px] text-muted-foreground"
                                >
                                    Hỗ trợ PNG, JPG, WEBP, PDF (Tối đa 10MB)
                                </span>
                            </label>
                            <div
                                v-if="taskFiles.length > 0"
                                class="flex flex-wrap gap-2 pt-2"
                            >
                                <span
                                    v-for="(f, i) in taskFiles"
                                    :key="f.name"
                                    class="flex items-center gap-1.5 rounded-md border border-border/70 bg-muted/30 px-2.5 py-1 text-xs text-foreground"
                                >
                                    {{ f.name }}
                                    <button
                                        class="text-muted-foreground hover:text-rose-500"
                                        @click="removeFile(i, 'task')"
                                    >
                                        <X class="size-3" />
                                    </button>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div
                        class="flex items-center justify-end gap-2 border-t border-border/60 pt-4"
                    >
                        <Button
                            variant="outline"
                            size="sm"
                            @click="activeTaskId = null"
                            >Hủy bỏ</Button
                        >
                        <Button
                            size="sm"
                            class="gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-medium shadow-xs"
                            :disabled="isProcessingTask"
                            @click="completeTask(activeTaskId!)"
                        >
                            <CheckCircle class="size-3.5" /> Xác nhận hoàn tất
                        </Button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ── Modal: Quét Mã QR / Barcode ── -->
        <Teleport to="body">
            <div
                v-if="showScanModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-background/80 p-4 backdrop-blur-sm"
                @click.self="
                    stopCameraScan();
                    showScanModal = false;
                    scanResult = null;
                "
            >
                <div
                    class="w-full max-w-md rounded-2xl border border-border/70 bg-card p-6 shadow-xl"
                >
                    <div
                        class="flex items-center justify-between border-b border-border/60 pb-4"
                    >
                        <div class="flex items-center gap-2.5">
                            <div
                                class="flex size-8 items-center justify-center rounded-lg bg-primary/10 text-primary"
                            >
                                <QrCode class="size-4" />
                            </div>
                            <h3
                                class="text-base font-semibold text-foreground"
                            >
                                Quét Mã QR / Barcode Kho
                            </h3>
                        </div>
                        <button
                            class="text-muted-foreground hover:text-foreground"
                            @click="
                                stopCameraScan();
                                showScanModal = false;
                                scanResult = null;
                            "
                        >
                            <X class="size-5" />
                        </button>
                    </div>

                    <div class="flex flex-col gap-4 py-4">
                        <p class="text-xs text-muted-foreground">
                            Nhập hoặc quét mã SKU nguyên liệu, mã số lô hàng (Lot/Batch) hoặc mã vị trí ô kệ kho:
                        </p>

                        <div class="flex items-center gap-2">
                            <Input
                                type="text"
                                v-model="scanInput"
                                placeholder="Nhập mã hoặc quét từ đầu đọc..."
                                class="h-9 text-xs font-mono"
                                @keyup.enter="handleScan"
                                autofocus
                            />
                            <Button
                                size="sm"
                                class="gap-1.5"
                                :disabled="isScanLoading"
                                @click="handleScan"
                            >
                                <Scan class="size-4" />
                            </Button>
                        </div>

                        <div class="flex flex-col gap-2">
                            <Button
                                v-if="!isCameraScanning"
                                variant="outline"
                                size="sm"
                                class="gap-2 text-xs"
                                @click="startCameraScan"
                            >
                                <QrCode class="size-3.5" /> Mở camera quét mã
                            </Button>
                            <div
                                v-if="isCameraScanning"
                                class="overflow-hidden rounded-xl border border-border bg-black"
                            >
                                <video
                                    id="warehouse-scan-video"
                                    class="aspect-video w-full object-cover"
                                    muted
                                    playsinline
                                ></video>
                                <Button
                                    size="sm"
                                    variant="secondary"
                                    class="m-2 text-xs"
                                    @click="stopCameraScan"
                                    >Dừng camera</Button
                                >
                            </div>
                            <p v-if="cameraError" class="text-xs text-rose-600 dark:text-rose-400">
                                {{ cameraError }}
                            </p>
                        </div>

                        <div
                            v-if="isScanLoading"
                            class="flex items-center justify-center gap-2 py-4 text-xs font-medium text-muted-foreground"
                        >
                            <RefreshCw
                                class="size-4 animate-spin text-primary"
                            />
                            Đang tra cứu thông tin trong hệ thống...
                        </div>

                        <div
                            v-if="scanResult"
                            class="mt-2 rounded-xl border p-4"
                            :class="
                                scanResult.status === 'not_found'
                                    ? 'border-rose-500/30 bg-rose-500/5'
                                    : 'border-border/70 bg-muted/20'
                            "
                        >
                            <div
                                v-if="scanResult.warning"
                                class="mb-2 flex items-center gap-1.5 rounded-lg border border-amber-500/30 bg-amber-500/10 p-2 text-xs font-medium text-amber-900 dark:text-amber-200"
                            >
                                <AlertTriangle class="size-4 shrink-0 text-amber-500" />
                                {{ scanResult.warning }}
                            </div>

                            <div
                                v-if="scanResult.type === 'ingredient'"
                                class="flex flex-col gap-1"
                            >
                                <Badge
                                    variant="outline"
                                    class="w-fit text-xs font-medium border-border/80 bg-background text-foreground"
                                    >Nguyên liệu</Badge
                                >
                                <h4
                                    class="text-sm font-semibold text-foreground"
                                >
                                    {{ scanResult.name }}
                                </h4>
                                <p class="text-xs text-muted-foreground">
                                    Mã SKU:
                                    <span
                                        class="font-mono font-medium text-foreground"
                                        >{{ scanResult.sku }}</span
                                    >
                                    • Đơn vị: {{ scanResult.unit }}
                                </p>
                            </div>

                            <div
                                v-else-if="scanResult.type === 'batch'"
                                class="flex flex-col gap-1"
                            >
                                <div class="flex items-center justify-between">
                                    <Badge
                                        variant="outline"
                                        class="w-fit text-xs font-medium border-border/80 bg-background text-foreground"
                                        >Lô hàng</Badge
                                    >
                                    <span
                                        class="rounded-md px-2 py-0.5 text-[11px] font-medium"
                                        :class="
                                            scanResult.status === 'ok'
                                                ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                                                : scanResult.status === 'locked'
                                                  ? 'bg-rose-500/10 text-rose-600 dark:text-rose-400'
                                                  : 'bg-amber-500/10 text-amber-600 dark:text-amber-400'
                                        "
                                    >
                                        {{
                                            scanResult.status === 'ok'
                                                ? '✓ Lô hợp lệ'
                                                : scanResult.status === 'locked'
                                                  ? '🔒 Đã bị khóa'
                                                  : '⚠ Cận/Hết hạn'
                                        }}
                                    </span>
                                </div>
                                <h4
                                    class="text-sm font-semibold text-foreground font-mono"
                                >
                                    {{ scanResult.batch_number }}
                                </h4>
                                <p
                                    class="text-xs text-muted-foreground"
                                >
                                    {{ scanResult.ingredient_name }} • Còn tồn:
                                    <span class="font-mono font-medium text-foreground">{{
                                        scanResult.quantity
                                    }}</span>
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    Hạn sử dụng:
                                    {{
                                        scanResult.expiry_date ||
                                        'Không ghi nhận'
                                    }}
                                </p>
                            </div>

                            <div
                                v-else-if="scanResult.type === 'location'"
                                class="flex flex-col gap-1"
                            >
                                <Badge
                                    variant="outline"
                                    class="w-fit text-xs font-medium border-border/80 bg-background text-foreground"
                                    >Vị trí lưu kho</Badge
                                >
                                <h4
                                    class="text-sm font-semibold text-foreground"
                                >
                                    {{ scanResult.code }} —
                                    {{ scanResult.name }}
                                </h4>
                                <p class="text-xs text-muted-foreground">
                                    Khu vực:
                                    <span
                                        class="font-medium text-foreground"
                                        >{{ scanResult.zone }}</span
                                    >
                                    <span
                                        v-if="scanResult.is_cold"
                                        class="ml-1 font-medium text-sky-600 dark:text-sky-400"
                                        >• ❄ Kho lạnh</span
                                    >
                                    <span
                                        v-if="scanResult.is_quarantine"
                                        class="ml-1 font-medium text-rose-600 dark:text-rose-400"
                                        >• 🚫 Khu cách ly</span
                                    >
                                </p>
                            </div>

                            <div
                                v-else
                                class="flex items-center gap-2 text-xs font-medium text-rose-600 dark:text-rose-400"
                            >
                                <AlertCircle class="size-4 shrink-0" />
                                {{
                                    scanResult.message ||
                                    'Mã không tồn tại trong hệ thống.'
                                }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ── Modal: Bảng Xác Nhận Soạn Hàng FEFO ── -->
        <Teleport to="body">
            <div
                v-if="showPickingModal && activePickingTask"
                class="fixed inset-0 z-50 flex items-center justify-center bg-background/80 p-4 backdrop-blur-sm"
                @click.self="showPickingModal = false"
            >
                <div
                    class="flex max-h-[90vh] w-full max-w-3xl flex-col rounded-2xl border border-border/70 bg-card shadow-xl"
                >
                    <!-- Modal Header -->
                    <div
                        class="flex items-center justify-between border-b border-border/60 p-5"
                    >
                        <div class="flex items-center gap-3">
                            <div
                                class="flex size-9 items-center justify-center rounded-lg bg-primary/10 text-primary"
                            >
                                <ClipboardList class="size-4" />
                            </div>
                            <div>
                                <h3
                                    class="text-base font-semibold text-foreground"
                                >
                                    Bảng Xác Nhận Soạn Hàng FEFO
                                </h3>
                                <p class="text-xs text-muted-foreground">
                                    Đơn cấp phát:
                                    <span
                                        class="font-semibold text-primary"
                                        >{{
                                            activePickingTask.supply_request
                                                ?.request_code
                                        }}</span
                                    >
                                    <span class="mx-1.5 text-muted-foreground">→</span>
                                    <span
                                        class="font-medium text-foreground"
                                        >{{
                                            formatBranchName(
                                                activePickingTask.supply_request
                                                    ?.to_branch,
                                            )
                                        }}</span
                                    >
                                </p>
                            </div>
                        </div>
                        <button
                            class="rounded-lg p-1 text-muted-foreground hover:text-foreground"
                            @click="showPickingModal = false"
                        >
                            <X class="size-5" />
                        </button>
                    </div>

                    <!-- Modal Body: Table of Items -->
                    <div class="flex-1 overflow-y-auto p-5">
                        <div
                            class="overflow-x-auto rounded-xl border border-border/60 bg-card"
                        >
                            <table class="w-full text-left text-xs">
                                <thead
                                    class="border-b border-border/60 bg-muted/50 font-medium text-muted-foreground"
                                >
                                    <tr>
                                        <th class="p-3">#</th>
                                        <th class="p-3">Nguyên liệu</th>
                                        <th class="p-3 text-right">SL Duyệt</th>
                                        <th class="p-3 text-right">
                                            SL Thực Soạn
                                        </th>
                                        <th class="p-3">Vị Trí Lấy Hàng</th>
                                        <th class="p-3">Mã Lô (Batch ID)</th>
                                    </tr>
                                </thead>
                                <tbody
                                    class="divide-y divide-border/60"
                                >
                                    <tr
                                        v-for="(item, idx) in pickingFormItems"
                                        :key="item.id"
                                        class="hover:bg-muted/20"
                                    >
                                        <td
                                            class="p-3 font-mono text-muted-foreground"
                                        >
                                            #{{ Number(idx) + 1 }}
                                        </td>
                                        <td class="p-3">
                                            <p
                                                class="font-medium text-foreground"
                                            >
                                                {{ item.ingredient_name }}
                                            </p>
                                            <span
                                                class="text-[11px] text-muted-foreground"
                                                >Đơn vị:
                                                {{ item.unit_symbol }}</span
                                            >
                                        </td>
                                        <td
                                            class="p-3 text-right font-mono font-medium text-muted-foreground"
                                        >
                                            {{ item.approved_quantity }}
                                            {{ item.unit_symbol }}
                                        </td>
                                        <td class="p-3 text-right">
                                            <Input
                                                type="number"
                                                v-model.number="
                                                    item.actual_dispatched_quantity
                                                "
                                                min="0"
                                                step="0.001"
                                                class="h-8 w-24 text-right font-mono text-xs font-semibold"
                                                :class="{
                                                    'border-rose-500/50 bg-rose-500/10 text-rose-600':
                                                        item.actual_dispatched_quantity !==
                                                        item.approved_quantity,
                                                }"
                                            />
                                        </td>
                                        <td class="p-3">
                                            <select
                                                v-model="
                                                    item.warehouse_location_id
                                                "
                                                class="h-8 w-full min-w-[160px] rounded-md border border-input bg-background px-2 text-xs shadow-xs focus:ring-1 focus:ring-ring text-foreground"
                                            >
                                                <option :value="null">
                                                    -- Tự động / Không chọn --
                                                </option>
                                                <option
                                                    v-for="loc in locations"
                                                    :key="loc.id"
                                                    :value="loc.id"
                                                >
                                                    {{
                                                        formatLocationName(loc)
                                                    }}
                                                </option>
                                            </select>
                                        </td>
                                        <td class="p-3">
                                            <Input
                                                type="number"
                                                v-model.number="
                                                    item.batch_id as any
                                                "
                                                placeholder="Tự chọn lô FEFO"
                                                class="h-8 w-28 font-mono text-xs"
                                            />
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Modal Footer -->
                    <div
                        class="flex items-center justify-between border-t border-border/60 p-4"
                    >
                        <p class="text-[11px] text-muted-foreground">
                            Tổng cộng:
                            <strong class="text-foreground"
                                >{{ pickingFormItems.length }} mặt hàng</strong
                            >
                            trong danh sách
                        </p>
                        <div class="flex items-center gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                @click="showPickingModal = false"
                            >
                                Hủy bỏ
                            </Button>
                            <Button
                                size="sm"
                                class="gap-1.5 text-xs font-medium"
                                :disabled="isSubmittingPicking"
                                @click="submitPickingModal"
                            >
                                <CheckCircle class="size-3.5" />
                                {{
                                    isSubmittingPicking
                                        ? 'Đang lưu...'
                                        : 'Xác nhận hoàn tất soạn hàng'
                                }}
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
