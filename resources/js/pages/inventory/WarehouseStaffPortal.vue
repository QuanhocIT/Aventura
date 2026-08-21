<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import {
    AlertCircle,
    AlertTriangle,
    ArrowRight,
    BadgeCheck,
    Box,
    CheckCircle,
    CheckSquare,
    ChevronRight,
    ClipboardList,
    Clock,
    HardHat,
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
import { computed, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    centralBranch: any;
    myTasks: Array<any>;
    taskSummary: any;
    myVouchers: Array<any>;
    myHandovers: Array<any>;
    locations: Array<any>;
    ingredients: Array<any>;
    canManageWarehouse: boolean;
    currentUser: any;
}>();

// ── State ─────────────────────────────────────────────────────────────────────

type TabId = 'today' | 'receiving' | 'putaway' | 'picking' | 'packing' | 'counting' | 'incident' | 'handover';

const activeTab = ref<TabId>('today');
const isLoading = ref(false);
const taskList = ref([...props.myTasks]);
const voucherList = ref([...props.myVouchers]);
const handoverList = ref([...props.myHandovers]);
const taskSummaryData = ref({ ...props.taskSummary });
const scanInput = ref('');
const scanResult = ref<any>(null);
const isScanLoading = ref(false);
const showScanModal = ref(false);

// GRN Form
const grnForm = ref({
    received_at: new Date().toISOString().slice(0, 16),
    supplier_id: null as number | null,
    notes: '',
    items: [] as Array<{
        ingredient_id: number | null;
        ingredient_name: string;
        expected_qty: number;
        actual_qty: number;
        unit_cost: number;
        lot_number: string;
        expiry_date: string;
        location_id: number | null;
        discrepancy_reason: string;
    }>,
});
const grnFiles = ref<File[]>([]);
const isSubmittingGrn = ref(false);

// Incident Form
const incidentForm = ref({
    incident_type: 'shortage' as 'shortage' | 'damage' | 'expired' | 'wrong_item' | 'other',
    description: '',
    ingredient_id: null as number | null,
    quantity_affected: null as number | null,
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

// ── Computed ──────────────────────────────────────────────────────────────────

const tabs = computed(() => [
    { id: 'today' as TabId, label: 'Việc Hôm Nay', icon: Zap, count: taskSummaryData.value.pending + taskSummaryData.value.in_progress },
    { id: 'receiving' as TabId, label: 'Nhập Hàng GRN', icon: PackageOpen, count: voucherList.value.filter(v => v.status === 'draft' || v.status === 'discrepancy').length },
    { id: 'putaway' as TabId, label: 'Cất Hàng', icon: Box, count: tasksByType('putaway').length },
    { id: 'picking' as TabId, label: 'Soạn Hàng FEFO', icon: ClipboardList, count: tasksByType('picking').length },
    { id: 'packing' as TabId, label: 'Đóng Gói', icon: Package, count: tasksByType('packing').length },
    { id: 'counting' as TabId, label: 'Kiểm Kê', icon: BadgeCheck, count: tasksByType('counting').length },
    { id: 'incident' as TabId, label: 'Báo Sự Cố', icon: AlertTriangle, count: 0 },
    { id: 'handover' as TabId, label: 'Bàn Giao Ca', icon: Truck, count: 0 },
]);

const overdueCount = computed(() =>
    taskList.value.filter(t => t.is_overdue || (t.due_at && new Date(t.due_at) < new Date() && !['completed', 'cancelled'].includes(t.status))).length,
);

function tasksByType(type: string) {
    return taskList.value.filter(t => t.task_type === type && t.status !== 'completed');
}

function priorityBadgeClass(priority: string) {
    switch (priority) {
        case 'urgent':
        case 'high':
            return 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/50 dark:text-rose-400 dark:border-rose-900';
        case 'normal':
            return 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/50 dark:text-amber-400 dark:border-amber-900';
        default:
            return 'bg-slate-100 text-slate-700 border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700';
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
        receiving: 'Nhận hàng (GRN)',
        putaway: 'Cất hàng vào vị trí',
        picking: 'Soạn hàng theo đơn',
        packing: 'Đóng gói kiện hàng',
        handover: 'Bàn giao ca',
        counting: 'Kiểm kê kho',
        incident: 'Xử lý sự cố',
        discrepancy_resolution: 'Xử lý sai lệch',
        shift_handover: 'Bàn giao ca',
    };

    return map[type] ?? type;
}

function statusBadgeClass(status: string) {
    switch (status) {
        case 'assigned':
            return 'bg-sky-50 text-sky-700 border-sky-200 dark:bg-sky-950/50 dark:text-sky-400 dark:border-sky-900';
        case 'in_progress':
            return 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/50 dark:text-amber-400 dark:border-amber-900';
        case 'completed':
            return 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/50 dark:text-emerald-400 dark:border-emerald-900';
        case 'cancelled':
            return 'bg-slate-100 text-slate-600 border-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:border-slate-700';
        default:
            return 'bg-slate-50 text-slate-600 border-slate-200';
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
            return 'bg-slate-100 text-slate-700 border-slate-200 dark:bg-slate-800 dark:text-slate-300';
        case 'confirmed':
            return 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/50 dark:text-emerald-400';
        case 'discrepancy':
            return 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/50 dark:text-rose-400';
        case 'pending_review':
            return 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/50 dark:text-amber-400';
        default:
            return 'bg-slate-100 text-slate-700 border-slate-200';
    }
}

function voucherStatusLabel(s: string): string {
    const map: Record<string, string> = {
        draft: 'Bản nháp',
        confirmed: 'Đã nhập kho',
        discrepancy: 'Có chênh lệch',
        pending_review: 'Chờ duyệt giải trình',
        closed: 'Đã hoàn tất',
    };

    return map[s] ?? s;
}

// ── API Actions ───────────────────────────────────────────────────────────────

async function refreshTasks() {
    isLoading.value = true;

    try {
        const { data } = await axios.get('/api/warehouse/my-tasks');
        taskList.value = data.tasks;
        taskSummaryData.value = data.summary;
        toast.success('Đã làm mới danh sách tác vụ.');
    } catch {
        toast.error('Không thể tải danh sách công việc.');
    } finally {
        isLoading.value = false;
    }
}

async function startTask(taskId: number) {
    isProcessingTask.value = true;

    try {
        const { data } = await axios.post(`/api/warehouse/tasks/${taskId}/start`);
        toast.success('Bắt đầu công việc thành công!');
        const idx = taskList.value.findIndex(t => t.id === taskId);
        if (idx !== -1) {
            taskList.value[idx] = { ...taskList.value[idx], ...data.task };
        }
        taskSummaryData.value.in_progress++;
        taskSummaryData.value.pending = Math.max(0, taskSummaryData.value.pending - 1);
    } catch (e: any) {
        toast.error(e.response?.data?.message ?? 'Lỗi khi bắt đầu công việc.');
    } finally {
        isProcessingTask.value = false;
    }
}

async function completeTask(taskId: number) {
    isProcessingTask.value = true;
    const formData = new FormData();
    if (taskResultNote.value) {
        formData.append('result_notes', taskResultNote.value);
    }
    taskFiles.value.forEach(f => formData.append('evidence[]', f));

    try {
        await axios.post(`/api/warehouse/tasks/${taskId}/complete`, formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        toast.success('Hoàn thành công việc xuất sắc!');
        await refreshTasks();
        activeTaskId.value = null;
        taskResultNote.value = '';
        taskFiles.value = [];
    } catch (e: any) {
        toast.error(e.response?.data?.message ?? 'Lỗi khi hoàn thành công việc.');
    } finally {
        isProcessingTask.value = false;
    }
}

// GRN
function addGrnItem() {
    grnForm.value.items.push({
        ingredient_id: null,
        ingredient_name: '',
        expected_qty: 0,
        actual_qty: 0,
        unit_cost: 0,
        lot_number: '',
        expiry_date: '',
        location_id: null,
        discrepancy_reason: '',
    });
}

function removeGrnItem(index: number) {
    grnForm.value.items.splice(index, 1);
}

async function submitGrn() {
    if (grnForm.value.items.length === 0) {
        toast.error('Vui lòng thêm ít nhất 1 nguyên liệu.');

        return;
    }

    isSubmittingGrn.value = true;
    const formData = new FormData();
    formData.append('received_at', grnForm.value.received_at);
    if (grnForm.value.notes) {
        formData.append('notes', grnForm.value.notes);
    }

    grnForm.value.items.forEach((item, i) => {
        if (item.ingredient_id) {
            formData.append(`items[${i}][ingredient_id]`, String(item.ingredient_id));
        }
        formData.append(`items[${i}][expected_qty]`, String(item.expected_qty));
        formData.append(`items[${i}][actual_qty]`, String(item.actual_qty));
        formData.append(`items[${i}][unit_cost]`, String(item.unit_cost));
        if (item.lot_number) {
            formData.append(`items[${i}][lot_number]`, item.lot_number);
        }
        if (item.expiry_date) {
            formData.append(`items[${i}][expiry_date]`, item.expiry_date);
        }
        if (item.location_id) {
            formData.append(`items[${i}][location_id]`, String(item.location_id));
        }
        if (item.discrepancy_reason) {
            formData.append(`items[${i}][discrepancy_reason]`, item.discrepancy_reason);
        }
    });

    grnFiles.value.forEach(f => formData.append('evidence[]', f));

    try {
        const { data } = await axios.post('/api/warehouse/receiving-vouchers', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        toast.success(data.message || 'Tạo phiếu nhận hàng thành công.');
        if (data.voucher) {
            voucherList.value.unshift(data.voucher);
        }
        grnForm.value.items = [];
        grnFiles.value = [];
        activeTab.value = 'today';
    } catch (e: any) {
        toast.error(e.response?.data?.message ?? 'Lỗi tạo phiếu nhận hàng.');
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

    isSubmittingIncident.value = true;
    const formData = new FormData();
    formData.append('incident_type', incidentForm.value.incident_type);
    formData.append('description', incidentForm.value.description);
    if (incidentForm.value.ingredient_id) {
        formData.append('ingredient_id', String(incidentForm.value.ingredient_id));
    }
    if (incidentForm.value.quantity_affected !== null) {
        formData.append('quantity_affected', String(incidentForm.value.quantity_affected));
    }
    incidentFiles.value.forEach(f => formData.append('evidence[]', f));

    try {
        const { data } = await axios.post('/api/warehouse/incidents', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        toast.success(data.message || 'Đã gửi báo cáo sự cố kho thành công.');
        incidentForm.value = { incident_type: 'shortage', description: '', ingredient_id: null, quantity_affected: null };
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
    isSubmittingHandover.value = true;

    try {
        const { data } = await axios.post('/api/warehouse/shift-handover', handoverForm.value);
        toast.success(data.message || 'Đã nộp biên bản bàn giao ca.');
        if (data.is_system_locked) {
            toast.warning(`Cảnh báo: Còn ${data.pending_tasks} task chưa hoàn thành trong ca.`);
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

// Scan
async function handleScan() {
    if (!scanInput.value.trim()) {
        return;
    }

    isScanLoading.value = true;
    scanResult.value = null;

    try {
        const { data } = await axios.post('/api/warehouse/scan', { code: scanInput.value.trim() });
        scanResult.value = data;
        if (data.warning) {
            toast.warning(data.warning);
        }
    } catch {
        scanResult.value = { type: 'unknown', status: 'not_found', message: 'Không tìm thấy thông tin mã quét trong hệ thống.' };
    } finally {
        isScanLoading.value = false;
    }
}

function handleFileInput(event: Event, target: 'grn' | 'incident' | 'task') {
    const files = (event.target as HTMLInputElement).files;
    if (!files) {
        return;
    }

    if (target === 'grn') {
        grnFiles.value = [...grnFiles.value, ...Array.from(files)];
    } else if (target === 'incident') {
        incidentFiles.value = [...incidentFiles.value, ...Array.from(files)];
    } else {
        taskFiles.value = [...taskFiles.value, ...Array.from(files)];
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

onMounted(() => {
    // Tự refresh task mỗi 5 phút
    setInterval(refreshTasks, 5 * 60 * 1000);
});
</script>

<template>
    <Head title="Cổng Nhân Sự & Tác Vụ Kho - Aventura" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6">
        <!-- ── Page Header ── -->
        <div class="flex flex-col gap-4 border-b border-slate-200/80 pb-5 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800">
            <div class="flex items-center gap-3">
                <div class="flex size-12 items-center justify-center rounded-2xl border border-amber-500/20 bg-amber-500/10 text-amber-600 shadow-sm dark:border-amber-500/30 dark:bg-amber-950/50 dark:text-amber-400">
                    <CheckSquare class="size-6" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-800 dark:text-slate-100">
                        Cổng Tác Vụ Nhân Viên Kho
                    </h1>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        {{ centralBranch?.name || 'Kho Tổng Sài Gòn' }} • Người thực thi: <span class="font-semibold text-slate-700 dark:text-slate-200">{{ currentUser?.name }}</span> ({{ currentUser?.job_title || 'Nhân viên kho' }})
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <Button
                    variant="outline"
                    class="gap-2 border-slate-200 bg-white hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:hover:bg-slate-800"
                    @click="showScanModal = true"
                >
                    <Scan class="size-4 text-amber-500" />
                    Quét Mã QR / Barcode
                </Button>

                <Button
                    variant="outline"
                    class="gap-2 border-slate-200 bg-white hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:hover:bg-slate-800"
                    :disabled="isLoading"
                    @click="refreshTasks"
                >
                    <RefreshCw class="size-4 text-slate-500" :class="{ 'animate-spin': isLoading }" />
                    Làm mới
                </Button>

                <Link v-if="canManageWarehouse" href="/warehouse/team">
                    <Button variant="outline" class="gap-2 border-indigo-200 text-indigo-700 hover:bg-indigo-50 dark:border-indigo-800 dark:text-indigo-300 dark:hover:bg-indigo-950">
                        <Users class="size-4" />
                        Quản Lý Đội Ngũ
                    </Button>
                </Link>
            </div>
        </div>

        <!-- ── Summary Metric Cards ── -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Card class="border-amber-200/60 bg-gradient-to-br from-amber-500/5 to-transparent shadow-sm dark:border-amber-950/30">
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardDescription class="text-xs font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400">Đang Thực Hiện</CardDescription>
                    <Zap class="size-4 text-amber-500" />
                </CardHeader>
                <CardContent>
                    <p class="text-2xl font-black text-amber-600 tabular-nums dark:text-amber-400">{{ taskSummaryData.in_progress }}</p>
                    <p class="mt-0.5 text-xs text-muted-foreground">nhiệm vụ đang tiến hành</p>
                </CardContent>
            </Card>

            <Card class="border-sky-200/60 bg-gradient-to-br from-sky-500/5 to-transparent shadow-sm dark:border-sky-950/30">
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardDescription class="text-xs font-bold uppercase tracking-wider text-sky-600 dark:text-sky-400">Chờ Xử Lý</CardDescription>
                    <Clock class="size-4 text-sky-500" />
                </CardHeader>
                <CardContent>
                    <p class="text-2xl font-black text-sky-600 tabular-nums dark:text-sky-400">{{ taskSummaryData.pending }}</p>
                    <p class="mt-0.5 text-xs text-muted-foreground">nhiệm vụ chờ bắt đầu</p>
                </CardContent>
            </Card>

            <Card class="border-rose-200/60 bg-gradient-to-br from-rose-500/5 to-transparent shadow-sm dark:border-rose-950/30">
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardDescription class="text-xs font-bold uppercase tracking-wider text-rose-600 dark:text-rose-400">Tác Vụ Quá Hạn</CardDescription>
                    <AlertTriangle class="size-4 text-rose-500" />
                </CardHeader>
                <CardContent>
                    <p class="text-2xl font-black text-rose-600 tabular-nums dark:text-rose-400">{{ overdueCount }}</p>
                    <p class="mt-0.5 text-xs text-muted-foreground">cần ưu tiên xử lý gấp</p>
                </CardContent>
            </Card>

            <Card class="border-emerald-200/60 bg-gradient-to-br from-emerald-500/5 to-transparent shadow-sm dark:border-emerald-950/30">
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardDescription class="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">Hoàn Thành Hôm Nay</CardDescription>
                    <CheckCircle class="size-4 text-emerald-500" />
                </CardHeader>
                <CardContent>
                    <p class="text-2xl font-black text-emerald-600 tabular-nums dark:text-emerald-400">{{ taskSummaryData.completed_today ?? 0 }}</p>
                    <p class="mt-0.5 text-xs text-muted-foreground">tác vụ đã hoàn tất chuẩn</p>
                </CardContent>
            </Card>
        </div>

        <!-- ── Navigation Tabs ── -->
        <div class="flex overflow-x-auto rounded-xl border border-slate-200 bg-slate-100/80 p-1.5 scrollbar-none dark:border-slate-800 dark:bg-slate-900/90">
            <button
                v-for="tab in tabs"
                :key="tab.id"
                class="flex shrink-0 items-center gap-2 rounded-lg px-3.5 py-2 text-xs font-semibold transition-all"
                :class="activeTab === tab.id
                    ? 'bg-white text-slate-900 shadow-sm dark:bg-slate-800 dark:text-slate-100'
                    : 'text-slate-600 hover:bg-white/60 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800/50 dark:hover:text-slate-200'"
                @click="activeTab = tab.id"
            >
                <component :is="tab.icon" class="size-4" :class="activeTab === tab.id ? 'text-amber-500' : 'text-slate-400'" />
                <span>{{ tab.label }}</span>
                <span
                    v-if="tab.count > 0"
                    class="ml-1 rounded-full px-1.5 py-0.5 text-[10px] font-bold"
                    :class="activeTab === tab.id ? 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300' : 'bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-300'"
                >
                    {{ tab.count }}
                </span>
            </button>
        </div>

        <!-- ── Tab Contents ── -->

        <!-- 1. HÔM NAY / VIỆC CỦA TÔI -->
        <div v-if="activeTab === 'today'" class="flex flex-col gap-4">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-bold text-slate-900 dark:text-slate-100">Danh Sách Tác Vụ Của Tôi</h2>
                    <p class="text-xs text-slate-500">Các công việc do Trưởng kho phân công trực tiếp cho bạn</p>
                </div>
            </div>

            <div v-if="taskList.length === 0" class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-slate-200 bg-slate-50/50 p-12 text-center dark:border-slate-800 dark:bg-slate-900/20">
                <div class="flex size-14 items-center justify-center rounded-2xl bg-amber-100 text-amber-600 dark:bg-amber-950/60 dark:text-amber-400">
                    <Warehouse class="size-7" />
                </div>
                <h3 class="mt-4 text-sm font-bold text-slate-800 dark:text-slate-200">Không có tác vụ nào đang chờ</h3>
                <p class="mt-1 max-w-sm text-xs text-slate-500 dark:text-slate-400">
                    Hiện tại bạn đã hoàn thành hết các nhiệm vụ được giao. Nhấn nút "Làm mới" hoặc quét mã QR để nhận công việc mới.
                </p>
            </div>

            <div v-else class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                <Card
                    v-for="task in taskList"
                    :key="task.id"
                    class="relative flex flex-col justify-between overflow-hidden transition-all hover:shadow-md"
                    :class="{
                        'border-rose-300 bg-rose-50/20 dark:border-rose-900/50': task.is_overdue,
                        'border-amber-300 bg-amber-50/20 dark:border-amber-900/50': task.status === 'in_progress',
                        'border-slate-200 dark:border-slate-800': task.status === 'assigned',
                        'border-emerald-200 bg-emerald-50/20 dark:border-emerald-900/40 opacity-80': task.status === 'completed',
                    }"
                >
                    <div class="p-5">
                        <div class="flex items-start justify-between gap-2">
                            <Badge variant="outline" class="gap-1.5 font-semibold" :class="priorityBadgeClass(task.priority)">
                                <span class="size-1.5 rounded-full" :class="task.priority === 'urgent' || task.priority === 'high' ? 'bg-rose-500' : 'bg-amber-500'"></span>
                                {{ taskTypeLabel(task.task_type) }}
                            </Badge>
                            <Badge variant="outline" class="font-semibold" :class="statusBadgeClass(task.status)">
                                {{ statusLabel(task.status) }}
                            </Badge>
                        </div>

                        <div v-if="task.supply_request" class="mt-3.5 flex items-center gap-1.5 text-xs font-semibold text-indigo-600 dark:text-indigo-400">
                            <span>{{ task.supply_request.request_code }}</span>
                            <ChevronRight class="size-3.5 text-slate-400" />
                            <span>{{ task.supply_request.to_branch }}</span>
                        </div>

                        <p v-if="task.notes" class="mt-2 text-xs leading-relaxed text-slate-600 dark:text-slate-300">
                            {{ task.notes }}
                        </p>

                        <div class="mt-4 flex flex-wrap items-center gap-3 border-t border-slate-100 pt-3 text-[11px] text-slate-500 dark:border-slate-800/80">
                            <span v-if="task.due_at" class="flex items-center gap-1" :class="{ 'font-bold text-rose-600 dark:text-rose-400': task.is_overdue }">
                                <Clock class="size-3.5" />
                                Hạn: {{ new Date(task.due_at).toLocaleString('vi-VN', { hour: '2-digit', minute: '2-digit', day: '2-digit', month: '2-digit' }) }}
                            </span>
                            <span class="flex items-center gap-1">
                                <HardHat class="size-3.5 text-slate-400" />
                                {{ priorityLabel(task.priority) }}
                            </span>
                        </div>
                    </div>

                    <div class="border-t border-slate-100 bg-slate-50/50 p-3 dark:border-slate-800 dark:bg-slate-900/30">
                        <div class="flex items-center justify-end gap-2">
                            <Button
                                v-if="task.status === 'assigned'"
                                size="sm"
                                class="w-full gap-1.5 bg-amber-600 text-xs font-semibold text-white hover:bg-amber-700 dark:bg-amber-500"
                                :disabled="isProcessingTask"
                                @click="startTask(task.id)"
                            >
                                <ArrowRight class="size-3.5" /> Bắt đầu thực hiện
                            </Button>
                            <Button
                                v-if="task.status === 'in_progress'"
                                size="sm"
                                class="w-full gap-1.5 bg-emerald-600 text-xs font-semibold text-white hover:bg-emerald-700 dark:bg-emerald-500"
                                @click="activeTaskId = task.id"
                            >
                                <CheckCircle class="size-3.5" /> Xác nhận hoàn tất
                            </Button>
                            <span v-if="task.status === 'completed'" class="flex w-full items-center justify-center gap-1 text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                                <BadgeCheck class="size-4" /> Đã hoàn thành
                            </span>
                        </div>
                    </div>
                </Card>
            </div>
        </div>

        <!-- 2. NHẬP HÀNG (GRN) -->
        <div v-if="activeTab === 'receiving'" class="flex flex-col gap-6">
            <Card class="border-slate-200 shadow-sm dark:border-slate-800">
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle class="text-lg font-bold text-slate-900 dark:text-slate-100">Tạo Phiếu Nhận Hàng (GRN)</CardTitle>
                            <CardDescription class="text-xs text-slate-500">Ghi nhận số lượng thực nhận, kiểm tra sai lệch và cất vào vị trí kho</CardDescription>
                        </div>
                        <Button size="sm" class="gap-1.5 bg-amber-600 text-xs font-semibold text-white hover:bg-amber-700" @click="addGrnItem">
                            <Plus class="size-3.5" /> Thêm nguyên liệu
                        </Button>
                    </div>
                </CardHeader>
                <CardContent class="flex flex-col gap-5">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="flex flex-col gap-1.5">
                            <Label class="text-xs font-bold text-slate-700 dark:text-slate-300">Thời gian nhận hàng *</Label>
                            <Input type="datetime-local" v-model="grnForm.received_at" class="h-9 text-xs" />
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <Label class="text-xs font-bold text-slate-700 dark:text-slate-300">Ghi chú chung</Label>
                            <Input v-model="grnForm.notes" placeholder="Tình trạng niêm phong, số xe giao hàng..." class="h-9 text-xs" />
                        </div>
                    </div>

                    <div v-if="grnForm.items.length === 0" class="flex flex-col items-center justify-center rounded-xl border border-dashed border-slate-200 p-8 text-center dark:border-slate-800">
                        <PackageOpen class="size-8 text-slate-400" />
                        <p class="mt-2 text-xs font-semibold text-slate-600 dark:text-slate-300">Chưa có nguyên liệu nào trong phiếu nhận</p>
                        <p class="text-[11px] text-slate-400">Nhấn nút "+ Thêm nguyên liệu" ở góc trên để bắt đầu</p>
                    </div>

                    <div v-else class="flex flex-col gap-3">
                        <div
                            v-for="(item, index) in grnForm.items"
                            :key="index"
                            class="rounded-xl border border-slate-200 bg-slate-50/60 p-4 dark:border-slate-800 dark:bg-slate-900/40"
                        >
                            <div class="flex items-center justify-between pb-3">
                                <span class="text-xs font-bold text-slate-800 dark:text-slate-200">#{{ index + 1 }} Mặt hàng</span>
                                <Button variant="ghost" size="sm" class="size-7 p-0 text-rose-500 hover:bg-rose-50 hover:text-rose-600" @click="removeGrnItem(index)">
                                    <Trash2 class="size-4" />
                                </Button>
                            </div>

                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                <div class="flex flex-col gap-1 sm:col-span-2">
                                    <Label class="text-[11px] font-semibold text-slate-600 dark:text-slate-400">Nguyên liệu *</Label>
                                    <select v-model="item.ingredient_id" class="h-9 rounded-md border border-slate-200 bg-white px-2.5 text-xs shadow-sm dark:border-slate-700 dark:bg-slate-900">
                                        <option :value="null">-- Chọn nguyên liệu --</option>
                                        <option v-for="ing in ingredients" :key="ing.id" :value="ing.id">
                                            {{ ing.name }} ({{ ing.sku }})
                                        </option>
                                    </select>
                                </div>

                                <div class="flex flex-col gap-1">
                                    <Label class="text-[11px] font-semibold text-slate-600 dark:text-slate-400">SL theo chứng từ</Label>
                                    <Input type="number" v-model.number="item.expected_qty" min="0" step="0.001" class="h-9 text-xs" />
                                </div>

                                <div class="flex flex-col gap-1">
                                    <Label class="text-[11px] font-semibold text-slate-600 dark:text-slate-400">SL thực nhận *</Label>
                                    <Input
                                        type="number"
                                        v-model.number="item.actual_qty"
                                        min="0"
                                        step="0.001"
                                        class="h-9 text-xs"
                                        :class="{ 'border-rose-400 bg-rose-50/50 dark:bg-rose-950/20': Math.abs(item.actual_qty - item.expected_qty) > 0.001 }"
                                    />
                                </div>

                                <div class="flex flex-col gap-1">
                                    <Label class="text-[11px] font-semibold text-slate-600 dark:text-slate-400">Mã lô (Lot Number)</Label>
                                    <Input type="text" v-model="item.lot_number" placeholder="LOT-..." class="h-9 text-xs" />
                                </div>

                                <div class="flex flex-col gap-1">
                                    <Label class="text-[11px] font-semibold text-slate-600 dark:text-slate-400">Hạn sử dụng</Label>
                                    <Input type="date" v-model="item.expiry_date" class="h-9 text-xs" />
                                </div>

                                <div class="flex flex-col gap-1 sm:col-span-2">
                                    <Label class="text-[11px] font-semibold text-slate-600 dark:text-slate-400">Vị trí cất hàng</Label>
                                    <select v-model="item.location_id" class="h-9 rounded-md border border-slate-200 bg-white px-2.5 text-xs shadow-sm dark:border-slate-700 dark:bg-slate-900">
                                        <option :value="null">-- Chọn vị trí kho --</option>
                                        <option v-for="loc in locations" :key="loc.id" :value="loc.id">
                                            {{ loc.code }} — {{ loc.name }}
                                        </option>
                                    </select>
                                </div>
                            </div>

                            <div v-if="Math.abs(item.actual_qty - item.expected_qty) > 0.001" class="mt-3 rounded-lg border border-rose-200 bg-rose-50/60 p-3 dark:border-rose-900/60 dark:bg-rose-950/30">
                                <Label class="flex items-center gap-1 text-[11px] font-bold text-rose-700 dark:text-rose-400">
                                    <AlertTriangle class="size-3.5" /> Bắt buộc giải trình chênh lệch SL *
                                </Label>
                                <Input
                                    v-model="item.discrepancy_reason"
                                    placeholder="Lý do chênh lệch: thiếu từ NCC, hao hụt vận chuyển..."
                                    class="mt-1.5 h-8 border-rose-300 bg-white text-xs dark:bg-slate-900"
                                />
                            </div>
                        </div>

                        <!-- Attachments -->
                        <div class="flex flex-col gap-1.5 pt-2">
                            <Label class="text-xs font-bold text-slate-700 dark:text-slate-300">Ảnh hóa đơn / Biên bản giao hàng</Label>
                            <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-slate-300 bg-slate-50/50 p-4 transition-all hover:bg-slate-100/60 dark:border-slate-700 dark:bg-slate-900/40">
                                <Upload class="size-5 text-slate-400" />
                                <span class="mt-1 text-xs font-semibold text-slate-600 dark:text-slate-300">Nhấn để chọn ảnh hoặc kéo thả chứng từ</span>
                                <input type="file" multiple accept="image/*,application/pdf" class="mt-2 text-xs" @change="handleFileInput($event, 'grn')" />
                            </div>
                            <div v-if="grnFiles.length > 0" class="flex flex-wrap gap-2 pt-2">
                                <span v-for="(f, i) in grnFiles" :key="f.name" class="flex items-center gap-1.5 rounded-full bg-slate-200 px-3 py-1 text-xs dark:bg-slate-800">
                                    {{ f.name }}
                                    <button class="text-rose-500" @click="removeFile(i, 'grn')"><X class="size-3" /></button>
                                </span>
                            </div>
                        </div>

                        <div class="flex justify-end pt-3">
                            <Button
                                class="gap-2 bg-amber-600 font-semibold text-white hover:bg-amber-700"
                                :disabled="isSubmittingGrn"
                                @click="submitGrn"
                            >
                                <PackageCheck class="size-4" />
                                {{ isSubmittingGrn ? 'Đang tạo phiếu...' : 'Tạo Phiếu Nhận Hàng' }}
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Lịch sử phiếu GRN -->
            <div class="flex flex-col gap-3">
                <h3 class="text-sm font-bold uppercase tracking-wider text-slate-500">Phiếu Nhận Hàng Gần Đây Của Tôi</h3>
                <div v-if="voucherList.length === 0" class="rounded-xl border border-slate-200 p-6 text-center text-xs text-slate-500 dark:border-slate-800">
                    Chưa có phiếu nhận hàng nào được tạo.
                </div>
                <div v-else class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <Card v-for="voucher in voucherList" :key="voucher.id" class="border-slate-200 shadow-sm dark:border-slate-800">
                        <CardContent class="p-4">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-indigo-600 dark:text-indigo-400">{{ voucher.voucher_code }}</span>
                                <Badge variant="outline" class="font-semibold" :class="voucherStatusBadgeClass(voucher.status)">
                                    {{ voucherStatusLabel(voucher.status) }}
                                </Badge>
                            </div>
                            <p class="mt-2 text-xs text-slate-500">
                                {{ voucher.items?.length ?? 0 }} nguyên liệu • Nhận: {{ new Date(voucher.received_at).toLocaleString('vi-VN') }}
                            </p>
                            <div v-if="(voucher.total_discrepancy_qty ?? 0) !== 0" class="mt-2 flex items-center gap-1 rounded bg-rose-50 px-2 py-1 text-xs font-bold text-rose-600 dark:bg-rose-950/40">
                                <AlertTriangle class="size-3.5" /> Chênh lệch: {{ voucher.total_discrepancy_qty }}
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>

        <!-- 3. CẤT HÀNG (PUTAWAY) -->
        <div v-if="activeTab === 'putaway'" class="flex flex-col gap-4">
            <h2 class="text-lg font-bold text-slate-900 dark:text-slate-100">Cất Hàng Vào Vị Trí Lưu Trữ</h2>
            <div v-if="tasksByType('putaway').length === 0" class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-slate-200 bg-slate-50/50 p-12 text-center dark:border-slate-800">
                <Box class="size-8 text-slate-400" />
                <h3 class="mt-3 text-sm font-bold text-slate-700 dark:text-slate-300">Không có tác vụ cất hàng</h3>
                <p class="text-xs text-slate-500">Tất cả hàng hóa mới nhận đã được đưa vào đúng vị trí lưu kho.</p>
            </div>
            <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <Card v-for="task in tasksByType('putaway')" :key="task.id" class="border-slate-200 shadow-sm dark:border-slate-800">
                    <CardContent class="p-5">
                        <div class="flex items-center justify-between">
                            <Badge variant="outline" class="border-amber-200 bg-amber-50 text-amber-700 dark:bg-amber-950/50">Cất hàng</Badge>
                            <Badge variant="outline" :class="statusBadgeClass(task.status)">{{ statusLabel(task.status) }}</Badge>
                        </div>
                        <p v-if="task.notes" class="mt-3 text-xs leading-relaxed text-slate-600 dark:text-slate-300">{{ task.notes }}</p>
                        <div class="mt-4 flex items-center justify-end gap-2 border-t border-slate-100 pt-3">
                            <Button v-if="task.status === 'assigned'" size="sm" class="gap-1.5 bg-amber-600 text-xs font-semibold text-white" @click="startTask(task.id)">
                                <ArrowRight class="size-3.5" /> Bắt đầu cất hàng
                            </Button>
                            <Button v-if="task.status === 'in_progress'" size="sm" class="gap-1.5 bg-emerald-600 text-xs font-semibold text-white" @click="activeTaskId = task.id">
                                <CheckCircle class="size-3.5" /> Hoàn thành
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- 4. SOẠN HÀNG (FEFO PICKING) -->
        <div v-if="activeTab === 'picking'" class="flex flex-col gap-4">
            <div class="rounded-xl border border-indigo-200 bg-indigo-50/70 p-4 text-indigo-900 dark:border-indigo-900/50 dark:bg-indigo-950/40 dark:text-indigo-200">
                <div class="flex items-center gap-2 font-bold text-xs">
                    <Shield class="size-4 text-indigo-600 dark:text-indigo-400" />
                    Quy tắc xuất hàng FEFO (First Expired, First Out)
                </div>
                <p class="mt-1 text-xs text-indigo-800 dark:text-indigo-300">
                    Hệ thống tự động ưu tiên các lô có hạn dùng ngắn nhất. Nhân viên kho chỉ chọn lô khác khi có lý do giải trình đặc biệt.
                </p>
            </div>

            <div v-if="tasksByType('picking').length === 0" class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-slate-200 bg-slate-50/50 p-12 text-center dark:border-slate-800">
                <ClipboardList class="size-8 text-slate-400" />
                <h3 class="mt-3 text-sm font-bold text-slate-700 dark:text-slate-300">Không có đơn soạn hàng</h3>
                <p class="text-xs text-slate-500">Hiện tại không có đơn cấp phát chi nhánh nào cần soạn.</p>
            </div>

            <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <Card v-for="task in tasksByType('picking')" :key="task.id" class="border-slate-200 shadow-sm dark:border-slate-800">
                    <CardContent class="p-5">
                        <div class="flex items-center justify-between">
                            <Badge variant="outline" class="border-indigo-200 bg-indigo-50 text-indigo-700 dark:bg-indigo-950/50">Soạn hàng</Badge>
                            <Badge variant="outline" :class="statusBadgeClass(task.status)">{{ statusLabel(task.status) }}</Badge>
                        </div>
                        <div v-if="task.supply_request" class="mt-2 text-xs font-bold text-indigo-600 dark:text-indigo-400">
                            {{ task.supply_request.request_code }} → {{ task.supply_request.to_branch }}
                        </div>
                        <p v-if="task.notes" class="mt-2 text-xs leading-relaxed text-slate-600 dark:text-slate-300">{{ task.notes }}</p>
                        <div class="mt-4 flex items-center justify-end gap-2 border-t border-slate-100 pt-3">
                            <Button v-if="task.status === 'assigned'" size="sm" class="gap-1.5 bg-amber-600 text-xs font-semibold text-white" @click="startTask(task.id)">
                                <ArrowRight class="size-3.5" /> Bắt đầu soạn
                            </Button>
                            <Button v-if="task.status === 'in_progress'" size="sm" class="gap-1.5 bg-emerald-600 text-xs font-semibold text-white" @click="activeTaskId = task.id">
                                <CheckCircle class="size-3.5" /> Đã soạn xong
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- 5. ĐÓNG GÓI (PACKING) -->
        <div v-if="activeTab === 'packing'" class="flex flex-col gap-4">
            <h2 class="text-lg font-bold text-slate-900 dark:text-slate-100">Đóng Gói & Niêm Phong Kiện Hàng</h2>
            <div v-if="tasksByType('packing').length === 0" class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-slate-200 bg-slate-50/50 p-12 text-center dark:border-slate-800">
                <Package class="size-8 text-slate-400" />
                <h3 class="mt-3 text-sm font-bold text-slate-700 dark:text-slate-300">Không có kiện hàng cần đóng gói</h3>
                <p class="text-xs text-slate-500">Mọi đơn hàng đã được niêm phong và chuyển cho bộ phận vận chuyển.</p>
            </div>
            <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <Card v-for="task in tasksByType('packing')" :key="task.id" class="border-slate-200 shadow-sm dark:border-slate-800">
                    <CardContent class="p-5">
                        <div class="flex items-center justify-between">
                            <Badge variant="outline" class="border-purple-200 bg-purple-50 text-purple-700 dark:bg-purple-950/50">Đóng gói</Badge>
                            <Badge variant="outline" :class="statusBadgeClass(task.status)">{{ statusLabel(task.status) }}</Badge>
                        </div>
                        <p v-if="task.notes" class="mt-3 text-xs leading-relaxed text-slate-600 dark:text-slate-300">{{ task.notes }}</p>
                        <div class="mt-4 flex items-center justify-end gap-2 border-t border-slate-100 pt-3">
                            <Button v-if="task.status === 'assigned'" size="sm" class="gap-1.5 bg-amber-600 text-xs font-semibold text-white" @click="startTask(task.id)">
                                <ArrowRight class="size-3.5" /> Bắt đầu đóng gói
                            </Button>
                            <Button v-if="task.status === 'in_progress'" size="sm" class="gap-1.5 bg-emerald-600 text-xs font-semibold text-white" @click="activeTaskId = task.id">
                                <CheckCircle class="size-3.5" /> Đã đóng gói xong
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- 6. KIỂM KÊ (COUNTING) -->
        <div v-if="activeTab === 'counting'" class="flex flex-col gap-4">
            <h2 class="text-lg font-bold text-slate-900 dark:text-slate-100">Kiểm Kê Tồn Kho Theo Phiên</h2>
            <div v-if="tasksByType('counting').length === 0" class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-slate-200 bg-slate-50/50 p-12 text-center dark:border-slate-800">
                <BadgeCheck class="size-8 text-slate-400" />
                <h3 class="mt-3 text-sm font-bold text-slate-700 dark:text-slate-300">Không có task kiểm kê phân công</h3>
                <p class="text-xs text-slate-500">Bạn có thể truy cập trang Kiểm Kê Tồn Kho để xem các phiên kiểm kê toàn kho.</p>
            </div>
            <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <Card v-for="task in tasksByType('counting')" :key="task.id" class="border-slate-200 shadow-sm dark:border-slate-800">
                    <CardContent class="p-5">
                        <div class="flex items-center justify-between">
                            <Badge variant="outline" class="border-emerald-200 bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50">Kiểm kê</Badge>
                            <Badge variant="outline" :class="statusBadgeClass(task.status)">{{ statusLabel(task.status) }}</Badge>
                        </div>
                        <p v-if="task.notes" class="mt-3 text-xs leading-relaxed text-slate-600 dark:text-slate-300">{{ task.notes }}</p>
                        <div class="mt-4 flex items-center justify-end gap-2 border-t border-slate-100 pt-3">
                            <Button v-if="task.status === 'assigned'" size="sm" class="gap-1.5 bg-amber-600 text-xs font-semibold text-white" @click="startTask(task.id)">
                                <ArrowRight class="size-3.5" /> Bắt đầu đếm
                            </Button>
                            <Button v-if="task.status === 'in_progress'" size="sm" class="gap-1.5 bg-emerald-600 text-xs font-semibold text-white" @click="activeTaskId = task.id">
                                <CheckCircle class="size-3.5" /> Nộp kết quả
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- 7. BÁO CÁO SỰ CỐ (INCIDENT) -->
        <div v-if="activeTab === 'incident'" class="flex flex-col gap-6">
            <Card class="border-slate-200 shadow-sm dark:border-slate-800">
                <CardHeader>
                    <CardTitle class="text-lg font-bold text-slate-900 dark:text-slate-100">Báo Cáo Sự Cố Kho & Chất Lượng</CardTitle>
                    <CardDescription class="text-xs text-slate-500">Phản ánh kịp thời hàng hư hại, ẩm mốc, hết hạn hoặc sai lệch số lượng để Trưởng kho xử lý</CardDescription>
                </CardHeader>
                <CardContent class="flex flex-col gap-4">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div class="flex flex-col gap-1.5">
                            <Label class="text-xs font-bold text-slate-700 dark:text-slate-300">Loại sự cố *</Label>
                            <select v-model="incidentForm.incident_type" class="h-9 rounded-md border border-slate-200 bg-white px-2.5 text-xs shadow-sm dark:border-slate-700 dark:bg-slate-900">
                                <option value="shortage">Thiếu hàng / Hao hụt</option>
                                <option value="damage">Hàng hỏng / Ẩm mốc / Rách bao bì</option>
                                <option value="expired">Hàng cận hoặc quá hạn sử dụng</option>
                                <option value="wrong_item">Nhầm mã hàng / Sai thông số</option>
                                <option value="other">Sự cố khác</option>
                            </select>
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <Label class="text-xs font-bold text-slate-700 dark:text-slate-300">Nguyên liệu liên quan</Label>
                            <select v-model="incidentForm.ingredient_id" class="h-9 rounded-md border border-slate-200 bg-white px-2.5 text-xs shadow-sm dark:border-slate-700 dark:bg-slate-900">
                                <option :value="null">-- Không xác định / Toàn bộ --</option>
                                <option v-for="ing in ingredients" :key="ing.id" :value="ing.id">{{ ing.name }}</option>
                            </select>
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <Label class="text-xs font-bold text-slate-700 dark:text-slate-300">Số lượng ảnh hưởng</Label>
                            <Input type="number" v-model.number="incidentForm.quantity_affected" min="0" step="0.001" placeholder="0" class="h-9 text-xs" />
                        </div>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <Label class="text-xs font-bold text-slate-700 dark:text-slate-300">Mô tả chi tiết sự cố *</Label>
                        <textarea
                            v-model="incidentForm.description"
                            rows="3"
                            placeholder="Mô tả cụ thể vị trí kệ hàng, thời điểm phát hiện, nguyên nhân sơ bộ..."
                            class="rounded-md border border-slate-200 bg-white p-3 text-xs shadow-sm dark:border-slate-700 dark:bg-slate-900"
                        ></textarea>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <Label class="text-xs font-bold text-slate-700 dark:text-slate-300">Ảnh hiện trường chứng minh</Label>
                        <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-slate-300 bg-slate-50/50 p-4 transition-all hover:bg-slate-100/60 dark:border-slate-700 dark:bg-slate-900/40">
                            <Upload class="size-5 text-slate-400" />
                            <span class="mt-1 text-xs font-semibold text-slate-600 dark:text-slate-300">Chụp ảnh hoặc chọn file từ máy</span>
                            <input type="file" multiple accept="image/*" class="mt-2 text-xs" @change="handleFileInput($event, 'incident')" />
                        </div>
                        <div v-if="incidentFiles.length > 0" class="flex flex-wrap gap-2 pt-2">
                            <span v-for="(f, i) in incidentFiles" :key="f.name" class="flex items-center gap-1.5 rounded-full bg-slate-200 px-3 py-1 text-xs dark:bg-slate-800">
                                {{ f.name }}
                                <button class="text-rose-500" @click="removeFile(i, 'incident')"><X class="size-3" /></button>
                            </span>
                        </div>
                    </div>

                    <div class="flex justify-end pt-2">
                        <Button
                            class="gap-2 bg-rose-600 font-semibold text-white hover:bg-rose-700"
                            :disabled="isSubmittingIncident"
                            @click="submitIncident"
                        >
                            <AlertTriangle class="size-4" />
                            {{ isSubmittingIncident ? 'Đang gửi...' : 'Gửi Báo Cáo Sự Cố' }}
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- 8. BÀN GIAO CA (HANDOVER) -->
        <div v-if="activeTab === 'handover'" class="flex flex-col gap-6">
            <!-- Alert if tasks remain -->
            <div v-if="taskSummaryData.pending > 0 || taskSummaryData.in_progress > 0" class="flex items-center gap-3 rounded-xl border border-amber-300 bg-amber-50 p-4 text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/50 dark:text-amber-200">
                <AlertTriangle class="size-5 text-amber-600" />
                <div class="text-xs">
                    <span class="font-bold">Lưu ý bàn giao:</span> Bạn vẫn còn <span class="font-bold underline">{{ taskSummaryData.pending + taskSummaryData.in_progress }} tác vụ</span> chưa hoàn tất. Hãy xử lý hoặc ghi chú rõ trong biên bản bàn giao.
                </div>
            </div>

            <Card class="border-slate-200 shadow-sm dark:border-slate-800">
                <CardHeader>
                    <CardTitle class="text-lg font-bold text-slate-900 dark:text-slate-100">Biên Bản Bàn Giao Cuối Ca</CardTitle>
                    <CardDescription class="text-xs text-slate-500">Chốt trạng thái vệ sinh kho, an toàn thiết bị, hàng tồn và bàn giao cho ca kế tiếp</CardDescription>
                </CardHeader>
                <CardContent class="flex flex-col gap-4">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div class="flex flex-col gap-1.5">
                            <Label class="text-xs font-bold text-slate-700 dark:text-slate-300">Ngày làm việc *</Label>
                            <Input type="date" v-model="handoverForm.shift_date" class="h-9 text-xs" />
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <Label class="text-xs font-bold text-slate-700 dark:text-slate-300">Ca làm việc *</Label>
                            <select v-model="handoverForm.shift_type" class="h-9 rounded-md border border-slate-200 bg-white px-2.5 text-xs shadow-sm dark:border-slate-700 dark:bg-slate-900">
                                <option value="morning">Ca Sáng (06:00 - 14:00)</option>
                                <option value="afternoon">Ca Chiều (14:00 - 22:00)</option>
                                <option value="evening">Ca Tối (18:00 - 23:00)</option>
                                <option value="night">Ca Đêm (22:00 - 06:00)</option>
                            </select>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <Label class="text-xs font-bold text-slate-700 dark:text-slate-300">Nhãn ca làm việc</Label>
                            <Input type="text" v-model="handoverForm.shift_label" placeholder="VD: Ca sáng Kho Tổng A" class="h-9 text-xs" />
                        </div>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <Label class="text-xs font-bold text-slate-700 dark:text-slate-300">Nội dung ghi chú bàn giao</Label>
                        <textarea
                            v-model="handoverForm.notes"
                            rows="3"
                            placeholder="Tình trạng kho bãi, hàng hóa cần kiểm tra đặc biệt, thiết bị xe nâng/kho lạnh..."
                            class="rounded-md border border-slate-200 bg-white p-3 text-xs shadow-sm dark:border-slate-700 dark:bg-slate-900"
                        ></textarea>
                    </div>

                    <div class="flex justify-end pt-2">
                        <Button
                            class="gap-2 bg-indigo-600 font-semibold text-white hover:bg-indigo-700"
                            :disabled="isSubmittingHandover"
                            @click="submitHandover"
                        >
                            <Truck class="size-4" />
                            {{ isSubmittingHandover ? 'Đang gửi...' : 'Nộp Biên Bản Bàn Giao' }}
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <div class="flex flex-col gap-3">
                <h3 class="text-sm font-bold uppercase tracking-wider text-slate-500">Lịch Sử Bàn Giao Gần Đây</h3>
                <div v-if="handoverList.length === 0" class="rounded-xl border border-slate-200 p-6 text-center text-xs text-slate-500 dark:border-slate-800">
                    Chưa có dữ liệu bàn giao ca trước đó.
                </div>
                <div v-else class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <Card v-for="handover in handoverList" :key="handover.id" class="border-slate-200 shadow-sm dark:border-slate-800">
                        <CardContent class="p-4">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-slate-800 dark:text-slate-200">{{ handover.shift_date }} — {{ handover.shift_label || handover.shift_type }}</span>
                                <Badge variant="outline" class="font-semibold" :class="handover.status === 'confirmed' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-700'">
                                    {{ handover.status === 'confirmed' ? 'Đã xác nhận' : 'Chờ xác nhận' }}
                                </Badge>
                            </div>
                            <p class="mt-2 text-xs text-slate-500">
                                Tạo lúc: {{ new Date(handover.created_at).toLocaleString('vi-VN') }}
                            </p>
                            <div v-if="handover.is_system_locked" class="mt-2 flex items-center gap-1 text-xs font-bold text-rose-600">
                                <AlertTriangle class="size-3.5" /> {{ handover.lock_reason }}
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>

        <!-- ── Modal: Hoàn Thành Tác Vụ ── -->
        <Teleport to="body">
        <div v-if="activeTaskId !== null" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
            <div class="w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-6 shadow-xl dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-center justify-between border-b border-slate-100 pb-4 dark:border-slate-800">
                    <h3 class="text-base font-bold text-slate-900 dark:text-slate-100">Xác Nhận Hoàn Thành Tác Vụ</h3>
                    <button class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200" @click="activeTaskId = null">
                        <X class="size-5" />
                    </button>
                </div>
                <div class="flex flex-col gap-4 py-4">
                    <div class="flex flex-col gap-1.5">
                        <Label class="text-xs font-bold text-slate-700 dark:text-slate-300">Ghi chú kết quả thực tế</Label>
                        <textarea
                            v-model="taskResultNote"
                            rows="3"
                            placeholder="Mô tả kết quả thực hiện, số lượng thực tế, vị trí đã cất..."
                            class="rounded-md border border-slate-200 bg-white p-3 text-xs shadow-sm dark:border-slate-700 dark:bg-slate-950"
                        ></textarea>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <Label class="text-xs font-bold text-slate-700 dark:text-slate-300">Ảnh bằng chứng hoàn thành (tuỳ chọn)</Label>
                        <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-slate-300 bg-slate-50/50 p-4 dark:border-slate-700 dark:bg-slate-950/40">
                            <Upload class="size-5 text-slate-400" />
                            <span class="mt-1 text-xs font-semibold text-slate-600 dark:text-slate-300">Chọn ảnh chứng từ / hàng hóa</span>
                            <input type="file" multiple accept="image/*,application/pdf" class="mt-2 text-xs" @change="handleFileInput($event, 'task')" />
                        </div>
                        <div v-if="taskFiles.length > 0" class="flex flex-wrap gap-2 pt-2">
                            <span v-for="(f, i) in taskFiles" :key="f.name" class="flex items-center gap-1.5 rounded-full bg-slate-200 px-3 py-1 text-xs dark:bg-slate-800">
                                {{ f.name }}
                                <button class="text-rose-500" @click="removeFile(i, 'task')"><X class="size-3" /></button>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-4 dark:border-slate-800">
                    <Button variant="outline" size="sm" @click="activeTaskId = null">Hủy bỏ</Button>
                    <Button
                        size="sm"
                        class="gap-1.5 bg-emerald-600 font-semibold text-white hover:bg-emerald-700"
                        :disabled="isProcessingTask"
                        @click="completeTask(activeTaskId!)"
                    >
                        <CheckCircle class="size-4" /> Xác nhận hoàn tất
                    </Button>
                </div>
            </div>
        </div>
        </Teleport>

        <!-- ── Modal: Quét Mã QR / Barcode ── -->
        <Teleport to="body">
        <div v-if="showScanModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4" @click.self="showScanModal = false; scanResult = null">
            <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-xl dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-center justify-between border-b border-slate-100 pb-4 dark:border-slate-800">
                    <div class="flex items-center gap-2">
                        <div class="flex size-8 items-center justify-center rounded-lg bg-amber-100 text-amber-600 dark:bg-amber-950 dark:text-amber-400">
                            <QrCode class="size-4" />
                        </div>
                        <h3 class="text-base font-bold text-slate-900 dark:text-slate-100">Quét Mã QR / Barcode Kho</h3>
                    </div>
                    <button class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200" @click="showScanModal = false; scanResult = null">
                        <X class="size-5" />
                    </button>
                </div>

                <div class="flex flex-col gap-4 py-4">
                    <p class="text-xs text-slate-500">
                        Nhập hoặc quét mã SKU nguyên liệu, mã số lô hàng (Lot/Batch) hoặc mã vị trí ô kệ kho:
                    </p>

                    <div class="flex items-center gap-2">
                        <Input
                            type="text"
                            v-model="scanInput"
                            placeholder="Nhập mã hoặc quét từ đầu đọc..."
                            class="h-10 text-xs font-semibold"
                            @keyup.enter="handleScan"
                            autofocus
                        />
                        <Button class="gap-1.5 bg-amber-600 font-semibold text-white hover:bg-amber-700" :disabled="isScanLoading" @click="handleScan">
                            <Scan class="size-4" />
                        </Button>
                    </div>

                    <div v-if="isScanLoading" class="flex items-center justify-center gap-2 py-4 text-xs font-semibold text-slate-500">
                        <RefreshCw class="size-4 animate-spin text-amber-500" /> Đang tra cứu thông tin trong hệ thống...
                    </div>

                    <div v-if="scanResult" class="mt-2 rounded-xl border p-4" :class="scanResult.status === 'not_found' ? 'border-rose-200 bg-rose-50/50 dark:border-rose-900/40 dark:bg-rose-950/20' : 'border-slate-200 bg-slate-50/80 dark:border-slate-800 dark:bg-slate-950/50'">
                        <div v-if="scanResult.warning" class="mb-2 flex items-center gap-1.5 rounded-lg bg-amber-100 p-2 text-xs font-bold text-amber-800 dark:bg-amber-950/80 dark:text-amber-300">
                            <AlertTriangle class="size-4 shrink-0" /> {{ scanResult.warning }}
                        </div>

                        <div v-if="scanResult.type === 'ingredient'" class="flex flex-col gap-1">
                            <Badge variant="outline" class="w-fit border-indigo-200 bg-indigo-50 text-indigo-700">Nguyên liệu</Badge>
                            <h4 class="text-sm font-bold text-slate-900 dark:text-slate-100">{{ scanResult.name }}</h4>
                            <p class="text-xs text-slate-500">Mã SKU: <span class="font-bold text-slate-700 dark:text-slate-300">{{ scanResult.sku }}</span> • Đơn vị: {{ scanResult.unit }}</p>
                        </div>

                        <div v-else-if="scanResult.type === 'batch'" class="flex flex-col gap-1">
                            <div class="flex items-center justify-between">
                                <Badge variant="outline" class="w-fit border-purple-200 bg-purple-50 text-purple-700">Lô hàng</Badge>
                                <span
                                    class="rounded px-2 py-0.5 text-[10px] font-bold"
                                    :class="scanResult.status === 'ok' ? 'bg-emerald-100 text-emerald-800' : scanResult.status === 'locked' ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800'"
                                >
                                    {{ scanResult.status === 'ok' ? '✓ Lô hợp lệ' : scanResult.status === 'locked' ? '🔒 Đã bị khóa' : '⚠ Cận/Hết hạn' }}
                                </span>
                            </div>
                            <h4 class="text-sm font-bold text-slate-900 dark:text-slate-100">{{ scanResult.batch_number }}</h4>
                            <p class="text-xs text-slate-600 dark:text-slate-300">{{ scanResult.ingredient_name }} • Còn tồn: <span class="font-bold">{{ scanResult.quantity }}</span></p>
                            <p class="text-xs text-slate-500">Hạn sử dụng: {{ scanResult.expiry_date || 'Không ghi nhận' }}</p>
                        </div>

                        <div v-else-if="scanResult.type === 'location'" class="flex flex-col gap-1">
                            <Badge variant="outline" class="w-fit border-sky-200 bg-sky-50 text-sky-700">Vị trí lưu kho</Badge>
                            <h4 class="text-sm font-bold text-slate-900 dark:text-slate-100">{{ scanResult.code }} — {{ scanResult.name }}</h4>
                            <p class="text-xs text-slate-500">
                                Khu vực: <span class="font-bold text-slate-700 dark:text-slate-300">{{ scanResult.zone }}</span>
                                <span v-if="scanResult.is_cold" class="ml-1 text-sky-600 font-semibold">• ❄ Kho lạnh</span>
                                <span v-if="scanResult.is_quarantine" class="ml-1 text-rose-600 font-semibold">• 🚫 Khu cách ly</span>
                            </p>
                        </div>

                        <div v-else class="flex items-center gap-2 text-xs font-bold text-rose-600 dark:text-rose-400">
                            <AlertCircle class="size-4 shrink-0" />
                            {{ scanResult.message || 'Mã không tồn tại trong hệ thống.' }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
        </Teleport>
    </div>
</template>
