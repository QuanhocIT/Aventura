<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import {
    AlertTriangle,
    ArrowRight,
    BadgeCheck,
    Box,
    CheckCircle,
    ChevronRight,
    ClipboardList,
    Clock,
    Package,
    PackageCheck,
    PackageOpen,
    QrCode,
    RefreshCw,
    RotateCcw,
    Scan,
    Shield,
    Truck,
    Upload,
    User,
    Warehouse,
    X,
    Zap,
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';

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
    { id: 'today' as TabId, label: 'Hôm Nay', icon: Zap, count: taskSummaryData.value.pending + taskSummaryData.value.in_progress },
    { id: 'receiving' as TabId, label: 'Nhập Hàng', icon: PackageOpen, count: 0 },
    { id: 'putaway' as TabId, label: 'Cất Hàng', icon: Box, count: tasksByType('putaway').length },
    { id: 'picking' as TabId, label: 'Soạn Hàng', icon: ClipboardList, count: tasksByType('picking').length },
    { id: 'packing' as TabId, label: 'Đóng Gói', icon: Package, count: tasksByType('packing').length },
    { id: 'counting' as TabId, label: 'Kiểm Kê', icon: BadgeCheck, count: tasksByType('counting').length },
    { id: 'incident' as TabId, label: 'Sự Cố', icon: AlertTriangle, count: 0 },
    { id: 'handover' as TabId, label: 'Bàn Giao Ca', icon: Truck, count: 0 },
]);

const overdueCount = computed(() => taskList.value.filter(t => t.is_overdue || (t.due_at && new Date(t.due_at) < new Date() && !['completed', 'cancelled'].includes(t.status))).length);

function tasksByType(type: string) {
    return taskList.value.filter(t => t.task_type === type && t.status !== 'completed');
}

function priorityColor(priority: string) {
    return priority === 'high' ? '#ef4444' : priority === 'normal' ? '#f59e0b' : '#6b7280';
}

function taskTypeLabel(type: string): string {
    const map: Record<string, string> = {
        receiving: 'Nhận hàng',
        putaway: 'Cất hàng',
        picking: 'Soạn hàng',
        packing: 'Đóng gói',
        handover: 'Bàn giao',
        counting: 'Kiểm kê',
        incident: 'Sự cố',
    };
    return map[type] ?? type;
}

function statusLabel(status: string): string {
    const map: Record<string, string> = {
        assigned: 'Chờ xử lý',
        in_progress: 'Đang thực hiện',
        completed: 'Hoàn thành',
        cancelled: 'Đã hủy',
    };
    return map[status] ?? status;
}

function voucherStatusLabel(s: string): string {
    const map: Record<string, string> = {
        draft: 'Nháp',
        confirmed: 'Đã xác nhận',
        discrepancy: 'Có chênh lệch',
        pending_review: 'Chờ xem xét',
        closed: 'Đã đóng',
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
        toast.success('Bắt đầu task thành công!');
        const idx = taskList.value.findIndex(t => t.id === taskId);
        if (idx !== -1) taskList.value[idx] = { ...taskList.value[idx], ...data.task };
        taskSummaryData.value.in_progress++;
        taskSummaryData.value.pending = Math.max(0, taskSummaryData.value.pending - 1);
    } catch (e: any) {
        toast.error(e.response?.data?.message ?? 'Lỗi khi bắt đầu task.');
    } finally {
        isProcessingTask.value = false;
    }
}

async function completeTask(taskId: number) {
    isProcessingTask.value = true;
    const formData = new FormData();
    if (taskResultNote.value) formData.append('result_notes', taskResultNote.value);
    taskFiles.value.forEach(f => formData.append('evidence[]', f));

    try {
        await axios.post(`/api/warehouse/tasks/${taskId}/complete`, formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        toast.success('Hoàn thành task!');
        await refreshTasks();
        activeTaskId.value = null;
        taskResultNote.value = '';
        taskFiles.value = [];
    } catch (e: any) {
        toast.error(e.response?.data?.message ?? 'Lỗi khi hoàn thành task.');
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
    if (grnForm.value.notes) formData.append('notes', grnForm.value.notes);
    grnForm.value.items.forEach((item, i) => {
        if (item.ingredient_id) formData.append(`items[${i}][ingredient_id]`, String(item.ingredient_id));
        formData.append(`items[${i}][expected_qty]`, String(item.expected_qty));
        formData.append(`items[${i}][actual_qty]`, String(item.actual_qty));
        formData.append(`items[${i}][unit_cost]`, String(item.unit_cost));
        if (item.lot_number) formData.append(`items[${i}][lot_number]`, item.lot_number);
        if (item.expiry_date) formData.append(`items[${i}][expiry_date]`, item.expiry_date);
        if (item.location_id) formData.append(`items[${i}][location_id]`, String(item.location_id));
        if (item.discrepancy_reason) formData.append(`items[${i}][discrepancy_reason]`, item.discrepancy_reason);
    });
    grnFiles.value.forEach(f => formData.append('evidence[]', f));

    try {
        const { data } = await axios.post('/api/warehouse/receiving-vouchers', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        toast.success(data.message);
        voucherList.value.unshift(data.voucher);
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
    if (incidentForm.value.ingredient_id) formData.append('ingredient_id', String(incidentForm.value.ingredient_id));
    if (incidentForm.value.quantity_affected !== null) formData.append('quantity_affected', String(incidentForm.value.quantity_affected));
    incidentFiles.value.forEach(f => formData.append('evidence[]', f));

    try {
        const { data } = await axios.post('/api/warehouse/incidents', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        toast.success(data.message);
        incidentForm.value = { incident_type: 'shortage', description: '', ingredient_id: null, quantity_affected: null };
        incidentFiles.value = [];
    } catch (e: any) {
        toast.error(e.response?.data?.message ?? 'Lỗi báo sự cố.');
    } finally {
        isSubmittingIncident.value = false;
    }
}

// Shift handover
async function submitHandover() {
    isSubmittingHandover.value = true;
    try {
        const { data } = await axios.post('/api/warehouse/shift-handover', handoverForm.value);
        toast.success(data.message);
        if (data.is_system_locked) {
            toast.warning(`Cảnh báo: Còn ${data.pending_tasks} task chưa hoàn thành.`);
        }
        handoverList.value.unshift(data.handover);
    } catch (e: any) {
        toast.error(e.response?.data?.message ?? 'Lỗi bàn giao ca.');
    } finally {
        isSubmittingHandover.value = false;
    }
}

// Scan
async function handleScan() {
    if (!scanInput.value.trim()) return;
    isScanLoading.value = true;
    scanResult.value = null;
    try {
        const { data } = await axios.post('/api/warehouse/scan', { code: scanInput.value.trim() });
        scanResult.value = data;
        if (data.warning) {
            toast.warning(data.warning);
        }
    } catch {
        scanResult.value = { type: 'unknown', status: 'not_found', message: 'Không tìm thấy mã.' };
    } finally {
        isScanLoading.value = false;
    }
}

function handleFileInput(event: Event, target: 'grn' | 'incident' | 'task') {
    const files = (event.target as HTMLInputElement).files;
    if (!files) return;
    if (target === 'grn') grnFiles.value = [...grnFiles.value, ...Array.from(files)];
    else if (target === 'incident') incidentFiles.value = [...incidentFiles.value, ...Array.from(files)];
    else taskFiles.value = [...taskFiles.value, ...Array.from(files)];
}

onMounted(() => {
    // Tự refresh task mỗi 5 phút
    setInterval(refreshTasks, 5 * 60 * 1000);
});
</script>

<template>
    <Head title="Portal Nhân Viên Kho" />

    <div class="staff-portal">
        <!-- Header -->
        <div class="portal-header">
            <div class="header-user">
                <div class="user-avatar">
                    <img v-if="currentUser.avatar_url" :src="currentUser.avatar_url" :alt="currentUser.name" />
                    <User v-else class="avatar-icon" />
                </div>
                <div class="user-info">
                    <div class="user-name">{{ currentUser.name }}</div>
                    <div class="user-role">{{ currentUser.job_title }}</div>
                </div>
            </div>
            <div class="header-actions">
                <button class="btn-scan" @click="showScanModal = true" title="Quét mã QR">
                    <Scan :size="20" />
                </button>
                <button class="btn-refresh" @click="refreshTasks" :disabled="isLoading" title="Làm mới">
                    <RefreshCw :size="18" :class="{ spin: isLoading }" />
                </button>
            </div>
        </div>

        <!-- Summary Chips -->
        <div class="summary-strip">
            <div class="chip chip-orange">
                <Zap :size="14" />
                <span>{{ taskSummaryData.in_progress }} đang làm</span>
            </div>
            <div class="chip chip-yellow">
                <Clock :size="14" />
                <span>{{ taskSummaryData.pending }} chờ</span>
            </div>
            <div v-if="overdueCount > 0" class="chip chip-red">
                <AlertTriangle :size="14" />
                <span>{{ overdueCount }} quá hạn</span>
            </div>
            <div class="chip chip-green">
                <CheckCircle :size="14" />
                <span>{{ taskSummaryData.completed_today ?? 0 }} xong hôm nay</span>
            </div>
        </div>

        <!-- Tab Navigation -->
        <div class="tab-nav">
            <button
                v-for="tab in tabs"
                :key="tab.id"
                class="tab-btn"
                :class="{ active: activeTab === tab.id }"
                @click="activeTab = tab.id"
            >
                <component :is="tab.icon" :size="18" />
                <span class="tab-label">{{ tab.label }}</span>
                <span v-if="tab.count > 0" class="tab-badge">{{ tab.count }}</span>
            </button>
        </div>

        <!-- Tab Content -->
        <div class="tab-content">
            <!-- ── HÔM NAY ── -->
            <div v-if="activeTab === 'today'" class="section">
                <h2 class="section-title">Việc Của Tôi Hôm Nay</h2>

                <div v-if="taskList.length === 0" class="empty-state">
                    <Warehouse :size="48" class="empty-icon" />
                    <p>Không có công việc nào được giao hôm nay.</p>
                </div>

                <div v-else class="task-list">
                    <div
                        v-for="task in taskList"
                        :key="task.id"
                        class="task-card"
                        :class="{
                            'task-overdue': task.is_overdue,
                            'task-in-progress': task.status === 'in_progress',
                            'task-completed': task.status === 'completed',
                        }"
                    >
                        <div class="task-header">
                            <div class="task-type-badge">
                                <span class="task-type-dot" :style="{ backgroundColor: priorityColor(task.priority) }"></span>
                                {{ taskTypeLabel(task.task_type) }}
                            </div>
                            <div class="task-status">{{ statusLabel(task.status) }}</div>
                        </div>

                        <div v-if="task.supply_request" class="task-meta">
                            <span>{{ task.supply_request.request_code }}</span>
                            <ChevronRight :size="14" />
                            <span>{{ task.supply_request.to_branch }}</span>
                        </div>

                        <div v-if="task.notes" class="task-notes">{{ task.notes }}</div>

                        <div class="task-footer">
                            <span v-if="task.due_at" class="task-due" :class="{ overdue: task.is_overdue }">
                                <Clock :size="12" />
                                {{ new Date(task.due_at).toLocaleString('vi-VN', { hour: '2-digit', minute: '2-digit', day: '2-digit', month: '2-digit' }) }}
                            </span>
                            <div class="task-actions">
                                <button
                                    v-if="task.status === 'assigned'"
                                    class="btn-start"
                                    @click="startTask(task.id)"
                                    :disabled="isProcessingTask"
                                >
                                    <ArrowRight :size="14" /> Bắt đầu
                                </button>
                                <button
                                    v-if="task.status === 'in_progress'"
                                    class="btn-complete"
                                    @click="activeTaskId = task.id"
                                >
                                    <CheckCircle :size="14" /> Hoàn thành
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Complete task modal -->
                <div v-if="activeTaskId !== null" class="modal-overlay" @click.self="activeTaskId = null">
                    <div class="modal-card">
                        <div class="modal-header">
                            <h3>Hoàn Thành Task</h3>
                            <button @click="activeTaskId = null"><X :size="20" /></button>
                        </div>
                        <div class="modal-body">
                            <label class="field-label">Ghi chú kết quả</label>
                            <textarea v-model="taskResultNote" class="textarea-field" rows="3" placeholder="Mô tả kết quả thực tế..."></textarea>
                            <label class="field-label mt-3">Ảnh bằng chứng (tuỳ chọn)</label>
                            <div class="file-upload-zone">
                                <Upload :size="20" />
                                <span>Chọn ảnh/PDF</span>
                                <input type="file" multiple accept="image/*,application/pdf" @change="handleFileInput($event, 'task')" />
                            </div>
                            <div v-if="taskFiles.length > 0" class="file-preview">
                                <span v-for="f in taskFiles" :key="f.name" class="file-chip">{{ f.name }}</span>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button class="btn-cancel" @click="activeTaskId = null">Hủy</button>
                            <button class="btn-confirm" @click="completeTask(activeTaskId!)" :disabled="isProcessingTask">
                                <CheckCircle :size="16" /> Xác nhận hoàn thành
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── NHẬP HÀNG (GRN) ── -->
            <div v-if="activeTab === 'receiving'" class="section">
                <h2 class="section-title">Tạo Phiếu Nhận Hàng (GRN)</h2>

                <div class="form-card">
                    <div class="form-row">
                        <label class="field-label">Thời gian nhận hàng *</label>
                        <input type="datetime-local" v-model="grnForm.received_at" class="input-field" />
                    </div>
                    <div class="form-row">
                        <label class="field-label">Ghi chú</label>
                        <textarea v-model="grnForm.notes" class="textarea-field" rows="2" placeholder="Ghi chú tình trạng hàng..."></textarea>
                    </div>

                    <div class="grn-items-header">
                        <span class="field-label">Danh sách nguyên liệu</span>
                        <button class="btn-add-item" @click="addGrnItem">+ Thêm</button>
                    </div>

                    <div v-if="grnForm.items.length === 0" class="empty-items">
                        <PackageOpen :size="32" />
                        <p>Nhấn "+ Thêm" để thêm nguyên liệu nhận</p>
                    </div>

                    <div v-for="(item, index) in grnForm.items" :key="index" class="grn-item-card">
                        <div class="grn-item-header">
                            <span class="grn-item-num">#{{ index + 1 }}</span>
                            <button class="btn-remove-item" @click="removeGrnItem(index)"><X :size="14" /></button>
                        </div>
                        <div class="form-row">
                            <label class="field-label-sm">Nguyên liệu *</label>
                            <select v-model="item.ingredient_id" class="select-field">
                                <option :value="null">-- Chọn nguyên liệu --</option>
                                <option v-for="ing in ingredients" :key="ing.id" :value="ing.id">
                                    {{ ing.name }} ({{ ing.sku }})
                                </option>
                            </select>
                        </div>
                        <div class="form-grid-2">
                            <div class="form-row">
                                <label class="field-label-sm">SL theo chứng từ</label>
                                <input type="number" v-model.number="item.expected_qty" min="0" step="0.001" class="input-field" />
                            </div>
                            <div class="form-row">
                                <label class="field-label-sm">SL thực nhận *</label>
                                <input type="number" v-model.number="item.actual_qty" min="0" step="0.001" class="input-field"
                                    :class="{ 'input-warn': Math.abs(item.actual_qty - item.expected_qty) > 0.001 }" />
                            </div>
                        </div>
                        <div class="form-grid-2">
                            <div class="form-row">
                                <label class="field-label-sm">Mã lô</label>
                                <input type="text" v-model="item.lot_number" class="input-field" placeholder="LOT-..." />
                            </div>
                            <div class="form-row">
                                <label class="field-label-sm">Hạn dùng</label>
                                <input type="date" v-model="item.expiry_date" class="input-field" />
                            </div>
                        </div>
                        <div class="form-row">
                            <label class="field-label-sm">Vị trí cất</label>
                            <select v-model="item.location_id" class="select-field">
                                <option :value="null">-- Chọn vị trí --</option>
                                <option v-for="loc in locations" :key="loc.id" :value="loc.id">
                                    {{ loc.code }} — {{ loc.name }}
                                </option>
                            </select>
                        </div>
                        <div v-if="Math.abs(item.actual_qty - item.expected_qty) > 0.001" class="form-row">
                            <label class="field-label-sm warn-label">Lý do chênh lệch *</label>
                            <textarea v-model="item.discrepancy_reason" class="textarea-field warn-border" rows="2"
                                placeholder="Bắt buộc khi có chênh lệch..."></textarea>
                        </div>
                    </div>

                    <div v-if="grnForm.items.length > 0" class="form-row">
                        <label class="field-label">Ảnh hóa đơn / ảnh hàng</label>
                        <div class="file-upload-zone">
                            <Upload :size="20" />
                            <span>Chụp hoặc chọn ảnh</span>
                            <input type="file" multiple accept="image/*,application/pdf" @change="handleFileInput($event, 'grn')" />
                        </div>
                        <div v-if="grnFiles.length > 0" class="file-preview">
                            <span v-for="f in grnFiles" :key="f.name" class="file-chip">{{ f.name }}</span>
                        </div>
                    </div>

                    <button v-if="grnForm.items.length > 0" class="btn-submit-grn" @click="submitGrn" :disabled="isSubmittingGrn">
                        <PackageCheck :size="18" />
                        {{ isSubmittingGrn ? 'Đang lưu...' : 'Tạo Phiếu Nhận Hàng' }}
                    </button>
                </div>

                <!-- Lịch sử phiếu GRN -->
                <h3 class="subsection-title">Phiếu gần đây của tôi</h3>
                <div v-if="voucherList.length === 0" class="empty-state-sm">Chưa có phiếu nhận hàng nào.</div>
                <div v-for="voucher in voucherList" :key="voucher.id" class="voucher-card">
                    <div class="voucher-header">
                        <span class="voucher-code">{{ voucher.voucher_code }}</span>
                        <span class="voucher-status" :class="`status-${voucher.status}`">
                            {{ voucherStatusLabel(voucher.status) }}
                        </span>
                    </div>
                    <div class="voucher-meta">
                        {{ voucher.items?.length ?? 0 }} nguyên liệu •
                        Nhận: {{ new Date(voucher.received_at).toLocaleString('vi-VN') }}
                    </div>
                    <div v-if="(voucher.total_discrepancy_qty ?? 0) !== 0" class="voucher-discrepancy">
                        <AlertTriangle :size="14" />
                        Chênh lệch: {{ voucher.total_discrepancy_qty }}
                    </div>
                </div>
            </div>

            <!-- ── CẤT HÀNG ── -->
            <div v-if="activeTab === 'putaway'" class="section">
                <h2 class="section-title">Cất Hàng Vào Vị Trí</h2>
                <div v-if="tasksByType('putaway').length === 0" class="empty-state">
                    <Box :size="48" class="empty-icon" />
                    <p>Không có task cất hàng nào được giao.</p>
                </div>
                <div v-for="task in tasksByType('putaway')" :key="task.id" class="task-card">
                    <div class="task-header">
                        <span class="task-type-badge">Cất hàng</span>
                        <span class="task-status">{{ statusLabel(task.status) }}</span>
                    </div>
                    <div v-if="task.supply_request" class="task-meta">{{ task.supply_request.request_code }}</div>
                    <div v-if="task.notes" class="task-notes">{{ task.notes }}</div>
                    <div class="task-footer">
                        <button v-if="task.status === 'assigned'" class="btn-start" @click="startTask(task.id)">
                            <ArrowRight :size="14" /> Bắt đầu cất
                        </button>
                        <button v-if="task.status === 'in_progress'" class="btn-complete" @click="activeTaskId = task.id">
                            <CheckCircle :size="14" /> Hoàn thành
                        </button>
                    </div>
                </div>
            </div>

            <!-- ── SOẠN HÀNG ── -->
            <div v-if="activeTab === 'picking'" class="section">
                <h2 class="section-title">Soạn Hàng Theo FEFO</h2>
                <div v-if="tasksByType('picking').length === 0" class="empty-state">
                    <ClipboardList :size="48" class="empty-icon" />
                    <p>Không có task soạn hàng nào được giao.</p>
                </div>
                <div v-for="task in tasksByType('picking')" :key="task.id" class="task-card">
                    <div class="task-header">
                        <span class="task-type-badge">Soạn hàng</span>
                        <span class="task-status">{{ statusLabel(task.status) }}</span>
                    </div>
                    <div v-if="task.supply_request" class="task-meta">
                        {{ task.supply_request.request_code }} → {{ task.supply_request.to_branch }}
                    </div>
                    <div v-if="task.notes" class="task-notes">{{ task.notes }}</div>
                    <div class="fefo-notice">
                        <Shield :size="12" /> Ưu tiên lô gần hết hạn nhất (FEFO)
                    </div>
                    <div class="task-footer">
                        <button v-if="task.status === 'assigned'" class="btn-start" @click="startTask(task.id)">
                            <ArrowRight :size="14" /> Bắt đầu soạn
                        </button>
                        <button v-if="task.status === 'in_progress'" class="btn-complete" @click="activeTaskId = task.id">
                            <CheckCircle :size="14" /> Hoàn thành soạn
                        </button>
                    </div>
                </div>
            </div>

            <!-- ── ĐÓNG GÓI ── -->
            <div v-if="activeTab === 'packing'" class="section">
                <h2 class="section-title">Đóng Gói & Niêm Phong</h2>
                <div v-if="tasksByType('packing').length === 0" class="empty-state">
                    <Package :size="48" class="empty-icon" />
                    <p>Không có task đóng gói nào được giao.</p>
                </div>
                <div v-for="task in tasksByType('packing')" :key="task.id" class="task-card">
                    <div class="task-header">
                        <span class="task-type-badge">Đóng gói</span>
                        <span class="task-status">{{ statusLabel(task.status) }}</span>
                    </div>
                    <div v-if="task.supply_request" class="task-meta">{{ task.supply_request.request_code }}</div>
                    <div v-if="task.notes" class="task-notes">{{ task.notes }}</div>
                    <div class="task-footer">
                        <button v-if="task.status === 'assigned'" class="btn-start" @click="startTask(task.id)">
                            <ArrowRight :size="14" /> Bắt đầu đóng gói
                        </button>
                        <button v-if="task.status === 'in_progress'" class="btn-complete" @click="activeTaskId = task.id">
                            <CheckCircle :size="14" /> Đã đóng gói xong
                        </button>
                    </div>
                </div>
            </div>

            <!-- ── KIỂM KÊ ── -->
            <div v-if="activeTab === 'counting'" class="section">
                <h2 class="section-title">Kiểm Kê Tồn Kho</h2>
                <div v-if="tasksByType('counting').length === 0" class="empty-state">
                    <BadgeCheck :size="48" class="empty-icon" />
                    <p>Không có task kiểm kê nào được giao.</p>
                </div>
                <div v-for="task in tasksByType('counting')" :key="task.id" class="task-card">
                    <div class="task-header">
                        <span class="task-type-badge">Kiểm kê</span>
                        <span class="task-status">{{ statusLabel(task.status) }}</span>
                    </div>
                    <div v-if="task.notes" class="task-notes">{{ task.notes }}</div>
                    <div class="task-footer">
                        <button v-if="task.status === 'assigned'" class="btn-start" @click="startTask(task.id)">
                            <ArrowRight :size="14" /> Bắt đầu kiểm kê
                        </button>
                        <button v-if="task.status === 'in_progress'" class="btn-complete" @click="activeTaskId = task.id">
                            <CheckCircle :size="14" /> Nộp kết quả kiểm kê
                        </button>
                    </div>
                </div>
            </div>

            <!-- ── SỰ CỐ ── -->
            <div v-if="activeTab === 'incident'" class="section">
                <h2 class="section-title">Báo Cáo Sự Cố</h2>
                <div class="form-card">
                    <div class="form-row">
                        <label class="field-label">Loại sự cố *</label>
                        <select v-model="incidentForm.incident_type" class="select-field">
                            <option value="shortage">Thiếu hàng</option>
                            <option value="damage">Hàng hỏng/ẩm mốc</option>
                            <option value="expired">Hàng hết hạn</option>
                            <option value="wrong_item">Nhầm hàng</option>
                            <option value="other">Khác</option>
                        </select>
                    </div>
                    <div class="form-row">
                        <label class="field-label">Nguyên liệu liên quan</label>
                        <select v-model="incidentForm.ingredient_id" class="select-field">
                            <option :value="null">-- Tất cả / Không xác định --</option>
                            <option v-for="ing in ingredients" :key="ing.id" :value="ing.id">{{ ing.name }}</option>
                        </select>
                    </div>
                    <div class="form-row">
                        <label class="field-label">Số lượng ảnh hưởng</label>
                        <input type="number" v-model.number="incidentForm.quantity_affected" min="0" step="0.001" class="input-field" placeholder="0" />
                    </div>
                    <div class="form-row">
                        <label class="field-label">Mô tả sự cố *</label>
                        <textarea v-model="incidentForm.description" class="textarea-field" rows="4"
                            placeholder="Mô tả chi tiết sự cố, vị trí phát hiện, thời điểm..."></textarea>
                    </div>
                    <div class="form-row">
                        <label class="field-label">Ảnh bằng chứng</label>
                        <div class="file-upload-zone">
                            <Upload :size="20" />
                            <span>Chụp ảnh hiện trường</span>
                            <input type="file" multiple accept="image/*" @change="handleFileInput($event, 'incident')" />
                        </div>
                        <div v-if="incidentFiles.length > 0" class="file-preview">
                            <span v-for="f in incidentFiles" :key="f.name" class="file-chip">{{ f.name }}</span>
                        </div>
                    </div>
                    <button class="btn-submit-incident" @click="submitIncident" :disabled="isSubmittingIncident">
                        <AlertTriangle :size="16" />
                        {{ isSubmittingIncident ? 'Đang gửi...' : 'Gửi Báo Cáo Sự Cố' }}
                    </button>
                </div>
            </div>

            <!-- ── BÀN GIAO CA ── -->
            <div v-if="activeTab === 'handover'" class="section">
                <h2 class="section-title">Bàn Giao Cuối Ca</h2>

                <!-- Warning if pending tasks -->
                <div v-if="taskSummaryData.pending > 0 || taskSummaryData.in_progress > 0" class="handover-warning">
                    <AlertTriangle :size="16" />
                    <span>Còn {{ taskSummaryData.pending + taskSummaryData.in_progress }} task chưa hoàn thành. Hãy xử lý trước khi bàn giao.</span>
                </div>

                <div class="form-card">
                    <div class="form-grid-2">
                        <div class="form-row">
                            <label class="field-label">Ngày làm việc *</label>
                            <input type="date" v-model="handoverForm.shift_date" class="input-field" />
                        </div>
                        <div class="form-row">
                            <label class="field-label">Ca làm việc *</label>
                            <select v-model="handoverForm.shift_type" class="select-field">
                                <option value="morning">Sáng</option>
                                <option value="afternoon">Chiều</option>
                                <option value="evening">Tối</option>
                                <option value="night">Đêm</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <label class="field-label">Nhãn ca (tuỳ chọn)</label>
                        <input type="text" v-model="handoverForm.shift_label" class="input-field" placeholder="VD: Ca sáng 06:00-14:00" />
                    </div>
                    <div class="form-row">
                        <label class="field-label">Ghi chú bàn giao</label>
                        <textarea v-model="handoverForm.notes" class="textarea-field" rows="4"
                            placeholder="Tình trạng kho, sự cố trong ca, hàng cần chú ý..."></textarea>
                    </div>
                    <button class="btn-submit-handover" @click="submitHandover" :disabled="isSubmittingHandover">
                        <Truck :size="18" />
                        {{ isSubmittingHandover ? 'Đang nộp...' : 'Nộp Bàn Giao Ca' }}
                    </button>
                </div>

                <!-- Lịch sử bàn giao -->
                <h3 class="subsection-title">Lịch sử bàn giao gần đây</h3>
                <div v-for="handover in handoverList" :key="handover.id" class="handover-card">
                    <div class="handover-header">
                        <span>{{ handover.shift_date }} — {{ handover.shift_label ?? handover.shift_type }}</span>
                        <span class="handover-status" :class="`status-${handover.status}`">
                            {{ handover.status === 'confirmed' ? 'Đã xác nhận' : handover.status === 'pending' ? 'Chờ xác nhận' : 'Nháp' }}
                        </span>
                    </div>
                    <div class="handover-meta">
                        Nộp lúc: {{ new Date(handover.created_at).toLocaleString('vi-VN') }}
                    </div>
                    <div v-if="handover.is_system_locked" class="handover-locked">
                        <AlertTriangle :size="12" /> {{ handover.lock_reason }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Scan Modal -->
        <div v-if="showScanModal" class="modal-overlay" @click.self="showScanModal = false; scanResult = null">
            <div class="modal-card scan-modal">
                <div class="modal-header">
                    <h3><QrCode :size="18" /> Quét Mã QR / Barcode</h3>
                    <button @click="showScanModal = false; scanResult = null"><X :size="20" /></button>
                </div>
                <div class="modal-body">
                    <p class="scan-hint">Nhập mã SKU nguyên liệu, mã lô hoặc mã vị trí kho:</p>
                    <div class="scan-input-row">
                        <input
                            type="text"
                            v-model="scanInput"
                            class="input-field scan-input"
                            placeholder="Nhập hoặc quét mã..."
                            @keyup.enter="handleScan"
                            autofocus
                        />
                        <button class="btn-scan-go" @click="handleScan" :disabled="isScanLoading">
                            <Scan :size="18" />
                        </button>
                    </div>

                    <div v-if="isScanLoading" class="scan-loading">
                        <RefreshCw :size="20" class="spin" /> Đang tra cứu...
                    </div>

                    <div v-if="scanResult" class="scan-result" :class="`scan-${scanResult.type}`">
                        <div v-if="scanResult.warning" class="scan-warning">
                            <AlertTriangle :size="16" /> {{ scanResult.warning }}
                        </div>
                        <div v-if="scanResult.type === 'ingredient'" class="scan-info">
                            <div class="scan-label">Nguyên liệu</div>
                            <div class="scan-name">{{ scanResult.name }}</div>
                            <div class="scan-detail">SKU: {{ scanResult.sku }} • Đơn vị: {{ scanResult.unit }}</div>
                        </div>
                        <div v-else-if="scanResult.type === 'batch'" class="scan-info">
                            <div class="scan-label">Lô hàng</div>
                            <div class="scan-name">{{ scanResult.batch_number }}</div>
                            <div class="scan-detail">
                                {{ scanResult.ingredient_name }} •
                                Còn: {{ scanResult.quantity }} •
                                HSD: {{ scanResult.expiry_date ?? 'N/A' }}
                            </div>
                            <div class="scan-status" :class="`scan-status-${scanResult.status}`">
                                {{ scanResult.status === 'ok' ? '✓ Lô hợp lệ' : scanResult.status === 'locked' ? '🔒 Lô đã khóa' : '⚠ Lô hết hạn' }}
                            </div>
                        </div>
                        <div v-else-if="scanResult.type === 'location'" class="scan-info">
                            <div class="scan-label">Vị trí kho</div>
                            <div class="scan-name">{{ scanResult.code }} — {{ scanResult.name }}</div>
                            <div class="scan-detail">
                                Khu: {{ scanResult.zone }}
                                <span v-if="scanResult.is_cold"> • ❄ Kho lạnh</span>
                                <span v-if="scanResult.is_quarantine"> • 🚫 Cách ly</span>
                            </div>
                        </div>
                        <div v-else class="scan-not-found">
                            <X :size="20" /> {{ scanResult.message }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* ── Base ─────────────────────────────────────────────────── */
.staff-portal {
    min-height: 100vh;
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 40%, #0f172a 100%);
    color: #f1f5f9;
    padding-bottom: 2rem;
    font-family: 'Inter', 'Segoe UI', sans-serif;
}

/* ── Header ─────────────────────────────────────────────────── */
.portal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 1.25rem;
    background: rgba(255, 255, 255, 0.04);
    border-bottom: 1px solid rgba(251, 191, 36, 0.15);
    backdrop-filter: blur(10px);
    position: sticky;
    top: 0;
    z-index: 50;
}
.header-user { display: flex; align-items: center; gap: 0.75rem; }
.user-avatar {
    width: 40px; height: 40px; border-radius: 50%;
    background: linear-gradient(135deg, #f59e0b, #ef4444);
    display: flex; align-items: center; justify-content: center;
    overflow: hidden;
}
.user-avatar img { width: 100%; height: 100%; object-fit: cover; }
.avatar-icon { color: white; }
.user-name { font-weight: 700; font-size: 0.95rem; color: #fbbf24; }
.user-role { font-size: 0.75rem; color: #94a3b8; }
.header-actions { display: flex; gap: 0.5rem; }
.btn-scan, .btn-refresh {
    width: 38px; height: 38px; border-radius: 50%;
    background: rgba(251, 191, 36, 0.15);
    border: 1px solid rgba(251, 191, 36, 0.3);
    color: #fbbf24; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    transition: all 0.2s;
}
.btn-scan:hover, .btn-refresh:hover { background: rgba(251, 191, 36, 0.3); }

/* ── Summary ─────────────────────────────────────────────────── */
.summary-strip {
    display: flex; gap: 0.5rem; padding: 0.75rem 1.25rem;
    overflow-x: auto; scrollbar-width: none;
}
.chip {
    display: flex; align-items: center; gap: 0.375rem;
    padding: 0.35rem 0.75rem; border-radius: 999px;
    font-size: 0.78rem; font-weight: 600; white-space: nowrap;
    flex-shrink: 0;
}
.chip-orange { background: rgba(249, 115, 22, 0.2); color: #fb923c; border: 1px solid rgba(249, 115, 22, 0.3); }
.chip-yellow { background: rgba(251, 191, 36, 0.15); color: #fbbf24; border: 1px solid rgba(251, 191, 36, 0.3); }
.chip-red { background: rgba(239, 68, 68, 0.2); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3); animation: pulse 2s infinite; }
.chip-green { background: rgba(34, 197, 94, 0.15); color: #4ade80; border: 1px solid rgba(34, 197, 94, 0.3); }

/* ── Tab Nav ─────────────────────────────────────────────────── */
.tab-nav {
    display: flex; gap: 0; overflow-x: auto; scrollbar-width: none;
    padding: 0 0.75rem 0;
    border-bottom: 2px solid rgba(255, 255, 255, 0.06);
}
.tab-btn {
    display: flex; flex-direction: column; align-items: center; gap: 0.2rem;
    padding: 0.65rem 0.9rem; cursor: pointer; position: relative;
    background: none; border: none; color: #64748b; font-size: 0.7rem;
    white-space: nowrap; flex-shrink: 0;
    transition: color 0.2s; border-bottom: 2px solid transparent; margin-bottom: -2px;
}
.tab-btn.active { color: #fbbf24; border-bottom-color: #fbbf24; }
.tab-label { font-weight: 600; }
.tab-badge {
    position: absolute; top: 0.3rem; right: 0.3rem;
    background: #ef4444; color: white;
    font-size: 0.6rem; font-weight: 700;
    width: 16px; height: 16px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
}

/* ── Tab Content ─────────────────────────────────────────────── */
.tab-content { padding: 1rem 1rem; }
.section { display: flex; flex-direction: column; gap: 0.75rem; }
.section-title {
    font-size: 1.1rem; font-weight: 700; color: #fbbf24;
    border-left: 3px solid #f59e0b; padding-left: 0.75rem;
    margin-bottom: 0.25rem;
}
.subsection-title {
    font-size: 0.9rem; font-weight: 600; color: #94a3b8;
    margin-top: 1rem; margin-bottom: 0.25rem;
}

/* ── Task Cards ─────────────────────────────────────────────── */
.task-card {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 12px; padding: 1rem;
    transition: all 0.2s;
}
.task-card:hover { border-color: rgba(251, 191, 36, 0.3); }
.task-card.task-overdue { border-color: rgba(239, 68, 68, 0.4); background: rgba(239, 68, 68, 0.05); }
.task-card.task-in-progress { border-color: rgba(249, 115, 22, 0.4); background: rgba(249, 115, 22, 0.05); }
.task-card.task-completed { opacity: 0.6; }
.task-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem; }
.task-type-badge {
    display: flex; align-items: center; gap: 0.4rem;
    font-size: 0.78rem; font-weight: 600; color: #fbbf24;
}
.task-type-dot { width: 8px; height: 8px; border-radius: 50%; }
.task-status { font-size: 0.72rem; color: #94a3b8; }
.task-meta { font-size: 0.8rem; color: #cbd5e1; display: flex; align-items: center; gap: 0.25rem; margin-bottom: 0.3rem; }
.task-notes { font-size: 0.8rem; color: #94a3b8; margin-bottom: 0.5rem; font-style: italic; }
.task-footer { display: flex; justify-content: space-between; align-items: center; margin-top: 0.75rem; }
.task-due { font-size: 0.72rem; color: #64748b; display: flex; align-items: center; gap: 0.3rem; }
.task-due.overdue { color: #ef4444; }
.task-actions { display: flex; gap: 0.5rem; }
.fefo-notice {
    font-size: 0.72rem; color: #4ade80;
    display: flex; align-items: center; gap: 0.3rem;
    background: rgba(34, 197, 94, 0.1); border-radius: 6px; padding: 0.25rem 0.5rem;
}

/* ── Buttons ─────────────────────────────────────────────────── */
.btn-start {
    display: flex; align-items: center; gap: 0.35rem;
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white; border: none; border-radius: 8px;
    padding: 0.4rem 0.9rem; font-size: 0.8rem; font-weight: 600;
    cursor: pointer; transition: all 0.2s;
}
.btn-start:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(245, 158, 11, 0.4); }
.btn-complete {
    display: flex; align-items: center; gap: 0.35rem;
    background: linear-gradient(135deg, #22c55e, #16a34a);
    color: white; border: none; border-radius: 8px;
    padding: 0.4rem 0.9rem; font-size: 0.8rem; font-weight: 600;
    cursor: pointer; transition: all 0.2s;
}
.btn-complete:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(34, 197, 94, 0.4); }
.btn-cancel {
    background: rgba(255, 255, 255, 0.08); color: #94a3b8;
    border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px;
    padding: 0.5rem 1rem; cursor: pointer; font-size: 0.85rem;
}
.btn-confirm {
    display: flex; align-items: center; gap: 0.4rem;
    background: linear-gradient(135deg, #22c55e, #16a34a);
    color: white; border: none; border-radius: 8px;
    padding: 0.5rem 1.25rem; font-weight: 600; cursor: pointer;
}
.btn-confirm:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-submit-grn, .btn-submit-incident, .btn-submit-handover {
    display: flex; align-items: center; justify-content: center; gap: 0.5rem;
    width: 100%; padding: 0.85rem;
    border: none; border-radius: 10px; font-weight: 700;
    font-size: 0.9rem; cursor: pointer; margin-top: 1rem;
    transition: all 0.2s;
}
.btn-submit-grn {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white;
}
.btn-submit-grn:hover { box-shadow: 0 6px 20px rgba(245, 158, 11, 0.4); }
.btn-submit-incident {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
}
.btn-submit-handover {
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    color: white;
}
.btn-submit-grn:disabled, .btn-submit-incident:disabled, .btn-submit-handover:disabled {
    opacity: 0.5; cursor: not-allowed;
}

/* ── Forms ──────────────────────────────────────────────────── */
.form-card {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 14px; padding: 1.25rem;
}
.form-row { display: flex; flex-direction: column; gap: 0.4rem; margin-bottom: 0.85rem; }
.form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
.field-label { font-size: 0.8rem; font-weight: 600; color: #94a3b8; }
.field-label-sm { font-size: 0.75rem; font-weight: 600; color: #94a3b8; }
.warn-label { color: #f87171; }
.input-field {
    background: rgba(255, 255, 255, 0.07);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 8px; padding: 0.6rem 0.85rem;
    color: #f1f5f9; font-size: 0.875rem; width: 100%;
    transition: border-color 0.2s;
}
.input-field:focus { outline: none; border-color: #fbbf24; }
.input-warn { border-color: rgba(239, 68, 68, 0.5); }
.select-field {
    background: rgba(255, 255, 255, 0.07);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 8px; padding: 0.6rem 0.85rem;
    color: #f1f5f9; font-size: 0.875rem; width: 100%;
}
.select-field option { background: #1e293b; }
.textarea-field {
    background: rgba(255, 255, 255, 0.07);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 8px; padding: 0.6rem 0.85rem;
    color: #f1f5f9; font-size: 0.875rem; width: 100%;
    resize: vertical; font-family: inherit;
}
.textarea-field:focus { outline: none; border-color: #fbbf24; }
.warn-border { border-color: rgba(239, 68, 68, 0.5) !important; }
.mt-3 { margin-top: 0.75rem; }

/* ── GRN Items ──────────────────────────────────────────────── */
.grn-items-header {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 0.75rem;
}
.btn-add-item {
    background: rgba(251, 191, 36, 0.15);
    color: #fbbf24; border: 1px solid rgba(251, 191, 36, 0.3);
    border-radius: 8px; padding: 0.35rem 0.85rem;
    font-size: 0.8rem; font-weight: 600; cursor: pointer;
    transition: all 0.2s;
}
.btn-add-item:hover { background: rgba(251, 191, 36, 0.3); }
.grn-item-card {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 10px; padding: 0.85rem;
    margin-bottom: 0.75rem;
}
.grn-item-header { display: flex; justify-content: space-between; margin-bottom: 0.5rem; }
.grn-item-num { font-size: 0.75rem; font-weight: 700; color: #fbbf24; }
.btn-remove-item {
    background: rgba(239, 68, 68, 0.15); border: none;
    color: #f87171; border-radius: 6px; padding: 0.2rem 0.5rem;
    cursor: pointer;
}
.empty-items {
    text-align: center; padding: 1.5rem; color: #64748b;
    display: flex; flex-direction: column; align-items: center; gap: 0.5rem;
    font-size: 0.85rem;
}

/* ── File Upload ────────────────────────────────────────────── */
.file-upload-zone {
    position: relative; border: 2px dashed rgba(255, 255, 255, 0.15);
    border-radius: 10px; padding: 1rem;
    display: flex; align-items: center; justify-content: center; gap: 0.5rem;
    color: #94a3b8; font-size: 0.85rem; cursor: pointer;
    transition: border-color 0.2s;
}
.file-upload-zone:hover { border-color: rgba(251, 191, 36, 0.4); color: #fbbf24; }
.file-upload-zone input[type="file"] {
    position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%;
}
.file-preview { display: flex; flex-wrap: wrap; gap: 0.4rem; margin-top: 0.5rem; }
.file-chip {
    background: rgba(251, 191, 36, 0.15); color: #fbbf24;
    border-radius: 6px; padding: 0.2rem 0.6rem; font-size: 0.72rem;
}

/* ── Voucher Cards ──────────────────────────────────────────── */
.voucher-card {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 10px; padding: 0.85rem;
    margin-bottom: 0.5rem;
}
.voucher-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.3rem; }
.voucher-code { font-weight: 700; color: #fbbf24; font-size: 0.85rem; }
.voucher-status { font-size: 0.72rem; padding: 0.2rem 0.6rem; border-radius: 999px; font-weight: 600; }
.status-draft { background: rgba(100, 116, 139, 0.2); color: #94a3b8; }
.status-confirmed { background: rgba(34, 197, 94, 0.15); color: #4ade80; }
.status-discrepancy { background: rgba(239, 68, 68, 0.15); color: #f87171; }
.status-pending_review { background: rgba(251, 191, 36, 0.15); color: #fbbf24; }
.status-closed { background: rgba(100, 116, 139, 0.2); color: #94a3b8; }
.voucher-meta { font-size: 0.75rem; color: #64748b; }
.voucher-discrepancy {
    display: flex; align-items: center; gap: 0.3rem;
    font-size: 0.75rem; color: #f87171; margin-top: 0.3rem;
}

/* ── Handover ───────────────────────────────────────────────── */
.handover-warning {
    display: flex; align-items: center; gap: 0.5rem;
    background: rgba(251, 191, 36, 0.1); border: 1px solid rgba(251, 191, 36, 0.3);
    border-radius: 10px; padding: 0.75rem 1rem;
    color: #fbbf24; font-size: 0.83rem; font-weight: 600;
}
.handover-card {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 10px; padding: 0.85rem;
    margin-bottom: 0.5rem;
}
.handover-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.25rem; }
.handover-status { font-size: 0.72rem; padding: 0.2rem 0.6rem; border-radius: 999px; font-weight: 600; }
.handover-meta { font-size: 0.75rem; color: #64748b; }
.handover-locked {
    display: flex; align-items: center; gap: 0.3rem;
    font-size: 0.75rem; color: #f87171; margin-top: 0.3rem;
}
.status-confirmed { background: rgba(34, 197, 94, 0.15); color: #4ade80; }
.status-pending { background: rgba(251, 191, 36, 0.15); color: #fbbf24; }
.status-draft { background: rgba(100, 116, 139, 0.2); color: #94a3b8; }

/* ── Empty States ───────────────────────────────────────────── */
.empty-state {
    text-align: center; padding: 3rem 1rem;
    display: flex; flex-direction: column; align-items: center; gap: 0.75rem;
    color: #475569;
}
.empty-icon { color: #334155; }
.empty-state p { font-size: 0.88rem; }
.empty-state-sm { text-align: center; color: #475569; font-size: 0.83rem; padding: 1rem; }

/* ── Modal ──────────────────────────────────────────────────── */
.modal-overlay {
    position: fixed; inset: 0; background: rgba(0, 0, 0, 0.75);
    display: flex; align-items: flex-end; justify-content: center;
    z-index: 100; padding: 0;
    animation: fadeIn 0.2s ease;
}
.modal-card {
    background: #1e293b; border-radius: 20px 20px 0 0;
    width: 100%; max-width: 600px; max-height: 85vh; overflow-y: auto;
    border: 1px solid rgba(255, 255, 255, 0.1);
    animation: slideUp 0.3s ease;
}
.scan-modal { border-radius: 16px; margin: auto; max-height: 80vh; }
.modal-header {
    display: flex; justify-content: space-between; align-items: center;
    padding: 1.25rem 1.5rem 1rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}
.modal-header h3 { font-weight: 700; color: #fbbf24; display: flex; align-items: center; gap: 0.5rem; }
.modal-header button { background: none; border: none; color: #94a3b8; cursor: pointer; }
.modal-body { padding: 1.25rem 1.5rem; }
.modal-footer {
    display: flex; gap: 0.75rem; justify-content: flex-end;
    padding: 1rem 1.5rem; border-top: 1px solid rgba(255, 255, 255, 0.08);
}

/* ── Scan ───────────────────────────────────────────────────── */
.scan-hint { font-size: 0.85rem; color: #94a3b8; margin-bottom: 0.75rem; }
.scan-input-row { display: flex; gap: 0.5rem; }
.scan-input { flex: 1; }
.btn-scan-go {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    border: none; border-radius: 8px; color: white;
    width: 48px; height: 48px; display: flex; align-items: center; justify-content: center;
    cursor: pointer; flex-shrink: 0;
}
.scan-loading { display: flex; align-items: center; gap: 0.5rem; color: #94a3b8; margin-top: 0.75rem; font-size: 0.85rem; }
.scan-result {
    margin-top: 1rem; border-radius: 12px; padding: 1rem;
    border: 1px solid rgba(255, 255, 255, 0.1);
    background: rgba(255, 255, 255, 0.04);
}
.scan-warning {
    display: flex; align-items: center; gap: 0.5rem;
    color: #f87171; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.5rem;
}
.scan-label { font-size: 0.72rem; font-weight: 600; color: #94a3b8; text-transform: uppercase; margin-bottom: 0.25rem; }
.scan-name { font-size: 1rem; font-weight: 700; color: #f1f5f9; margin-bottom: 0.25rem; }
.scan-detail { font-size: 0.8rem; color: #94a3b8; }
.scan-status { font-size: 0.8rem; font-weight: 600; margin-top: 0.5rem; }
.scan-status-ok { color: #4ade80; }
.scan-status-locked { color: #f87171; }
.scan-status-expired { color: #fb923c; }
.scan-not-found { display: flex; align-items: center; gap: 0.5rem; color: #f87171; font-size: 0.88rem; }

/* ── Animations ─────────────────────────────────────────────── */
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes slideUp { from { transform: translateY(100%); } to { transform: translateY(0); } }
@keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.6; } }
.spin { animation: spin 1s linear infinite; }
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }

/* ── Responsive ─────────────────────────────────────────────── */
@media (max-width: 640px) {
    .form-grid-2 { grid-template-columns: 1fr; }
    .tab-btn { padding: 0.5rem 0.65rem; }
    .tab-label { font-size: 0.62rem; }
}
</style>
