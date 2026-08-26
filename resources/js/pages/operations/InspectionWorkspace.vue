<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import {
    AlertTriangle,
    Building2,
    Calendar,
    Camera,
    Check,
    CheckCircle2,
    ClipboardCheck,
    ClipboardList,
    ExternalLink,
    FileText,
    FileWarning,
    Filter,
    Image as ImageIcon,
    Layers,
    Link2,
    MapPin,
    Play,
    Plus,
    RefreshCw,
    Save,
    Search,
    ShieldCheck,
    Sparkles,
    User,
    UserCheck,
    Users,
    X,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useOfflineMutationQueue } from '@/composables/useOfflineMutationQueue';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

type Inspection = any;

const props = defineProps<{
    inspections: Inspection[];
    templates: any[];
    branches: any[];
    inspectors: any[];
    employees: any[];
    plans: any[];
    pagination?: { current_page: number; last_page: number; per_page: number; total: number };
    filters?: { search: string; status: string };
    currentUserId: number;
    capabilities: Record<string, boolean>;
    branchContext?: { scope: string; active_branch_id: number | null };
}>();

const selectedId = ref<number | null>(props.inspections[0]?.id ?? null);
const selectedInspectionDetail = ref<any | null>(null);
const isLoadingDetail = ref(false);
const isCreating = ref(false);
const isSaving = ref(false);
const { enqueue, pendingCount, failedCount, retryFailed } = useOfflineMutationQueue();
const searchQuery = ref(props.filters?.search ?? '');
const statusFilter = ref<string>(props.filters?.status ?? 'all');
const participantSearch = ref('');

const resultNotes = ref<Record<number, string>>({});
const findingNotes = ref<Record<number, string>>({});
const checklistPhotos = ref<Record<number, string>>({});

const actionForm = ref({
    title: '',
    description: '',
    root_cause: '',
    corrective_action: '',
    preventive_action: '',
    assigned_to: '' as string | number,
    priority: 'normal',
    due_date: '',
});

const inspectionForm = ref({
    branch_id: '' as string | number,
    inspection_plan_id: '' as string | number,
    title: '',
    inspection_type: 'routine',
    scheduled_at: '',
    lead_inspector_id: '' as string | number,
    participants: [] as number[],
    scope: '',
    location_note: '',
});

const completeForm = ref({ conclusion: '', score: '' as string | number, risk_level: '' });
const caseLinkForm = ref({ link_type: 'incident', link_id: '' as string | number });

const selectedInspection = computed(() => {
    if (selectedInspectionDetail.value?.id === selectedId.value) return selectedInspectionDetail.value;
    return props.inspections.find((inspection) => inspection.id === selectedId.value) ?? null;
});

let detailRequest = 0;

async function loadInspectionDetails(id: number | null = selectedId.value) {
    const requestId = ++detailRequest;
    selectedInspectionDetail.value = null;
    if (!id) return;

    isLoadingDetail.value = true;
    try {
        const response = await axios.get(`/api/operational-audit/inspections/${id}`);
        if (requestId === detailRequest && selectedId.value === id) {
            selectedInspectionDetail.value = response.data.data;
        }
    } catch (error) {
        if (requestId === detailRequest) notifyError(error);
    } finally {
        if (requestId === detailRequest) isLoadingDetail.value = false;
    }
}

watch(
    () => [props.inspections, props.branchContext?.scope, props.branchContext?.active_branch_id],
    () => {
        if (!props.inspections.some((inspection) => inspection.id === selectedId.value)) {
            selectedId.value = props.inspections[0]?.id ?? null;
        }
        void loadInspectionDetails();
    },
    { deep: true },
);

watch(
    () => selectedId.value,
    (id) => void loadInspectionDetails(id),
    { immediate: true },
);

const filteredInspections = computed(() => {
    return props.inspections;
});

const paginationPages = computed(() => {
    const current = props.pagination?.current_page ?? 1;
    const last = props.pagination?.last_page ?? 1;
    const start = Math.max(1, current - 2);
    const end = Math.min(last, current + 2);
    return Array.from({ length: end - start + 1 }, (_, index) => start + index);
});

let filterTimer: ReturnType<typeof setTimeout> | null = null;

watch([searchQuery, statusFilter], () => {
    if (filterTimer) clearTimeout(filterTimer);
    filterTimer = setTimeout(() => navigateInspectionList(1), 350);
});

function navigateInspectionList(page = 1) {
    selectedInspectionDetail.value = null;
    router.get(
        '/operations/inspection-workspace',
        {
            page,
            search: searchQuery.value.trim() || undefined,
            status: statusFilter.value === 'all' ? undefined : statusFilter.value,
        },
        { preserveState: true, preserveScroll: true, only: ['inspections', 'pagination', 'filters'] },
    );
}

const branchEmployees = computed(() => {
    const branchId = Number(selectedInspection.value?.branch?.id ?? inspectionForm.value.branch_id);
    return props.employees.filter((employee) => !branchId || Number(employee.branch_id) === branchId);
});

const filteredParticipantCandidates = computed(() => {
    if (!participantSearch.value) return props.employees;
    const q = participantSearch.value.toLowerCase();
    return props.employees.filter(
        (emp) => emp.name?.toLowerCase().includes(q) || emp.email?.toLowerCase().includes(q) || emp.branch?.name?.toLowerCase().includes(q)
    );
});

const selectedChecklist = computed(() => {
    const inspection = selectedInspection.value;
    if (!inspection) return [];
    const branchId = Number(inspection.branch?.id ?? 0);
    const results = new Map((inspection.checklist_results ?? []).map((result: any) => [Number(result.item_id), result]));

    return props.templates
        .filter((template) => template.applies_to_all_branches || (template.branch_ids ?? []).includes(branchId))
        .flatMap((template) => template.items.map((item: any) => ({
            ...item,
            template_name: template.name,
            completion: results.get(Number(item.id)) ?? null,
        })));
});

const statusLabels: Record<string, string> = {
    draft: 'Nháp',
    planned: 'Đã lên lịch',
    in_progress: 'Đang kiểm tra',
    completed: 'Đã hoàn tất',
    cancelled: 'Đã hủy',
    open: 'Mở',
    accepted: 'Đã nhận',
    submitted: 'Chờ xác minh',
    verified: 'Đã xác minh',
    rejected: 'Cần làm lại',
};

const inspectionTypeLabels: Record<string, string> = {
    routine: 'Định kỳ',
    thematic: 'Theo chuyên đề',
    surprise: 'Đột xuất',
    follow_up: 'Tái kiểm',
};

function statusLabel(status: string) {
    return statusLabels[status] ?? status;
}

function checklistResultLabel(result?: string | null) {
    return ({ pass: 'Đạt', fail: 'Không đạt', na: 'N/A' } as Record<string, string>)[result ?? ''] ?? 'Chưa ghi nhận';
}

function statusClass(status: string) {
    return (
        {
            draft: 'bg-zinc-500/10 text-zinc-400 border-zinc-500/20',
            planned: 'bg-blue-500/10 text-blue-400 border-blue-500/20',
            in_progress: 'bg-amber-500/10 text-amber-400 border-amber-500/20',
            completed: 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
            cancelled: 'bg-rose-500/10 text-rose-400 border-rose-500/20',
            open: 'bg-zinc-500/10 text-zinc-400 border-zinc-500/20',
            accepted: 'bg-blue-500/10 text-blue-400 border-blue-500/20',
            in_progress_action: 'bg-amber-500/10 text-amber-400 border-amber-500/20',
            submitted: 'bg-purple-500/10 text-purple-400 border-purple-500/20',
            verified: 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
            rejected: 'bg-rose-500/10 text-rose-400 border-rose-500/20',
        }[status] ?? 'bg-muted text-muted-foreground border-border'
    );
}

function toggleParticipant(employeeId: number) {
    const idx = inspectionForm.value.participants.indexOf(employeeId);
    if (idx > -1) {
        inspectionForm.value.participants.splice(idx, 1);
    } else {
        inspectionForm.value.participants.push(employeeId);
    }
}

function getEmployeeName(id: number) {
    const emp = props.employees.find((e) => e.id === id);
    return emp ? emp.name : `NV #${id}`;
}

function notifyError(error: any) {
    toast.error(error?.response?.data?.message ?? 'Không thể thực hiện thao tác.');
}

function reload() {
    selectedInspectionDetail.value = null;
    router.reload({ only: ['inspections', 'pagination', 'filters'] });
}

async function createInspection() {
    if (!inspectionForm.value.branch_id) {
        toast.error('Vui lòng chọn chi nhánh kiểm tra.');
        return;
    }
    if (!inspectionForm.value.title.trim()) {
        toast.error('Vui lòng nhập tên phiên kiểm tra.');
        return;
    }
    if (!inspectionForm.value.scope.trim()) {
        toast.error('Vui lòng nhập phạm vi / mục tiêu kiểm tra.');
        return;
    }

    isSaving.value = true;
    try {
        const response = await axios.post('/api/operational-audit/inspections', inspectionForm.value);
        toast.success(response.data.message);
        isCreating.value = false;
        selectedId.value = response.data.data.id;
        reload();
    } catch (error) {
        notifyError(error);
    } finally {
        isSaving.value = false;
    }
}

async function startInspection() {
    if (!selectedInspection.value) return;
    try {
        const response = await axios.post(`/api/operational-audit/inspections/${selectedInspection.value.id}/start`);
        toast.success(response.data.message);
        reload();
    } catch (error) {
        notifyError(error);
    }
}

async function completeInspection() {
    if (!selectedInspection.value) return;
    isSaving.value = true;
    try {
        const response = await axios.post(`/api/operational-audit/inspections/${selectedInspection.value.id}/complete`, completeForm.value);
        toast.success(response.data.message);
        reload();
    } catch (error) {
        notifyError(error);
    } finally {
        isSaving.value = false;
    }
}

async function saveChecklist(item: any, result: 'pass' | 'fail' | 'na') {
    if (!selectedInspection.value) return;
    const payload = {
        item_id: item.id,
        result,
        notes: resultNotes.value[item.id] ?? '',
        finding_notes: findingNotes.value[item.id] ?? '',
        photo: checklistPhotos.value[item.id] ?? '',
    };

    try {
        const response = await axios.post(`/api/operational-audit/inspections/${selectedInspection.value.id}/checklist`, payload);
        toast.success(response.data.message);
        reload();
    } catch (error: any) {
        if (!error.response || (typeof navigator !== 'undefined' && !navigator.onLine)) {
            enqueue(`/api/operational-audit/inspections/${selectedInspection.value.id}/checklist`, payload);
            toast.info('Đã lưu kết quả checklist, sẽ tự đồng bộ khi có mạng.');
            return;
        }
        notifyError(error);
    }
}

function setChecklistPhoto(itemId: number, event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = () => {
        const source = new Image();
        source.onload = () => {
            const maxDimension = 1280;
            const scale = Math.min(1, maxDimension / Math.max(source.width, source.height));
            const canvas = document.createElement('canvas');
            canvas.width = Math.round(source.width * scale);
            canvas.height = Math.round(source.height * scale);
            canvas.getContext('2d')?.drawImage(source, 0, 0, canvas.width, canvas.height);
            checklistPhotos.value[itemId] = canvas.toDataURL('image/jpeg', 0.82);
        };
        source.src = String(reader.result ?? '');
    };
    reader.readAsDataURL(file);
}

async function createAction() {
    if (!selectedInspection.value) return;
    if (!actionForm.value.title.trim()) {
        toast.error('Vui lòng nhập tên hành động khắc phục.');
        return;
    }
    isSaving.value = true;
    try {
        const response = await axios.post(`/api/operational-audit/inspections/${selectedInspection.value.id}/actions`, actionForm.value);
        toast.success(response.data.message);
        actionForm.value = { title: '', description: '', root_cause: '', corrective_action: '', preventive_action: '', assigned_to: '', priority: 'normal', due_date: '' };
        reload();
    } catch (error) {
        notifyError(error);
    } finally {
        isSaving.value = false;
    }
}

async function uploadEvidence(event: Event) {
    if (!selectedInspection.value) return;
    const file = (event.target as HTMLInputElement).files?.[0];
    if (!file) return;

    const payload = new FormData();
    payload.append('file', file);
    payload.append('collection', 'inspection');
    payload.append('operational_inspection_id', String(selectedInspection.value.id));

    try {
        const response = await axios.post('/api/operational-audit/evidence', payload, { headers: { 'Content-Type': 'multipart/form-data' } });
        toast.success(response.data.message);
        reload();
    } catch (error) {
        notifyError(error);
    }
    (event.target as HTMLInputElement).value = '';
}

async function updateAction(action: any, status: string) {
    try {
        const response = await axios.patch(`/api/operational-audit/actions/${action.id}`, {
            status,
            submission_notes: status === 'submitted' ? 'Đã hoàn tất hành động và gửi xác minh.' : undefined,
            verification_notes: status === 'verified' ? 'Đã kiểm tra kết quả tại hiện trường.' : undefined,
        });
        toast.success(response.data.message);
        reload();
    } catch (error) {
        notifyError(error);
    }
}

async function linkCase() {
    if (!selectedInspection.value || !caseLinkForm.value.link_id) return;
    try {
        const response = await axios.post('/api/operational-audit/links', {
            operational_inspection_id: selectedInspection.value.id,
            link_type: caseLinkForm.value.link_type,
            link_id: Number(caseLinkForm.value.link_id),
        });
        toast.success(response.data.message);
        caseLinkForm.value.link_id = '';
        reload();
    } catch (error) {
        notifyError(error);
    }
}
</script>

<template>
    <Head title="Phiên kiểm tra hiện trường" />

    <div class="inspection-workspace mx-auto w-full max-w-[1720px] space-y-7 p-5 md:space-y-8 md:p-8 xl:px-10">
        <!-- Banner Header -->
        <div class="relative overflow-hidden rounded-3xl border border-indigo-500/20 bg-gradient-to-br from-indigo-500/10 via-card to-card p-7 shadow-sm backdrop-blur-md md:p-8">
            <div class="absolute -right-10 -top-10 size-48 rounded-full bg-indigo-500/10 blur-3xl pointer-events-none"></div>
            <div class="relative z-10 flex flex-col justify-between gap-4 md:flex-row md:items-center">
                <div class="flex items-start gap-4">
                    <div class="flex size-12 shrink-0 items-center justify-center rounded-2xl border border-indigo-400/30 bg-indigo-600 text-white shadow-lg shadow-indigo-600/20">
                        <ClipboardCheck class="size-6" />
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-[11px] font-bold tracking-wider text-indigo-400 uppercase">Tác nghiệp hiện trường</span>
                            <Badge variant="outline" class="border-indigo-500/30 bg-indigo-500/10 text-indigo-300 text-[10px]">
                                Operations Inspection
                            </Badge>
                        </div>
                        <h1 class="mt-1 text-3xl font-extrabold tracking-tight text-white md:text-4xl">Phiên kiểm tra & CAPA</h1>
                        <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-300 md:text-base">
                            Thực thi kiểm tra thực địa tại chi nhánh: ghi nhận checklist, lưu bằng chứng hình ảnh, lập phát hiện và giao việc khắc phục sai phạm.
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <Button v-if="capabilities.create" class="h-11 gap-2 rounded-xl bg-indigo-600 px-5 text-sm text-white shadow-md shadow-indigo-600/20 hover:bg-indigo-500" @click="isCreating = !isCreating">
                        <Plus class="size-4" /> {{ isCreating ? 'Đóng biểu mẫu' : 'Tạo phiên kiểm tra' }}
                    </Button>
                </div>
            </div>
        </div>

        <!-- Section Tạo phiên kiểm tra (Modern Animated Card) -->
        <transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="transform -translate-y-2 opacity-0"
            enter-to-class="transform translate-y-0 opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="transform translate-y-0 opacity-100"
            leave-to-class="transform -translate-y-2 opacity-0"
        >
            <section v-if="isCreating" class="rounded-3xl border border-indigo-500/30 bg-card p-6 shadow-2xl backdrop-blur-xl md:p-8">
                <div class="flex items-center justify-between border-b border-border/60 pb-4">
                    <div class="flex items-center gap-3">
                        <div class="flex size-9 items-center justify-center rounded-xl bg-indigo-500/10 text-indigo-400">
                            <Sparkles class="size-5" />
                        </div>
                        <div>
                            <h2 class="text-lg font-bold text-foreground">Lập phiên kiểm tra hiện trường mới</h2>
                            <p class="text-sm text-muted-foreground">Khởi tạo hồ sơ thực thi kiểm tra cho chi nhánh trong phạm vi được giao</p>
                        </div>
                    </div>
                    <Button variant="ghost" size="icon" class="rounded-full text-muted-foreground hover:bg-muted" @click="isCreating = false">
                        <X class="size-4" />
                    </Button>
                </div>

                <div class="mt-6 grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                    <!-- Chi nhánh -->
                    <div class="space-y-1.5">
                        <label class="flex items-center gap-1.5 text-sm font-semibold text-foreground">
                            <Building2 class="size-3.5 text-indigo-400" /> Chi nhánh kiểm tra <span class="text-rose-500">*</span>
                        </label>
                        <select
                            v-model="inspectionForm.branch_id"
                            class="h-11 w-full rounded-xl border border-input bg-background/80 px-3 text-sm text-foreground transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30"
                        >
                            <option value="" disabled>Chọn chi nhánh...</option>
                            <option v-for="branch in branches" :key="branch.id" :value="branch.id">{{ branch.name }}</option>
                        </select>
                    </div>

                    <!-- Kế hoạch -->
                    <div class="space-y-1.5">
                        <label class="flex items-center gap-1.5 text-sm font-semibold text-foreground">
                            <Layers class="size-3.5 text-indigo-400" /> Kế hoạch thanh tra liên quan
                        </label>
                        <select
                            v-model="inspectionForm.inspection_plan_id"
                            class="h-11 w-full rounded-xl border border-input bg-background/80 px-3 text-sm text-foreground transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30"
                        >
                            <option value="">Không gắn kế hoạch (Kiểm tra độc lập)</option>
                            <option v-for="plan in plans" :key="plan.id" :value="plan.id">{{ plan.plan_code }} · {{ plan.title }}</option>
                        </select>
                    </div>

                    <!-- Loại kiểm tra -->
                    <div class="space-y-1.5">
                        <label class="flex items-center gap-1.5 text-sm font-semibold text-foreground">
                            <Filter class="size-3.5 text-indigo-400" /> Loại hình kiểm tra
                        </label>
                        <select
                            v-model="inspectionForm.inspection_type"
                            class="h-11 w-full rounded-xl border border-input bg-background/80 px-3 text-sm text-foreground transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30"
                        >
                            <option value="routine">Định kỳ</option>
                            <option value="thematic">Theo chuyên đề</option>
                            <option value="surprise">Đột xuất</option>
                            <option value="follow_up">Tái kiểm tra</option>
                        </select>
                    </div>

                    <!-- Tên phiên -->
                    <div class="space-y-1.5 md:col-span-2">
                        <label class="flex items-center gap-1.5 text-sm font-semibold text-foreground">
                            <FileText class="size-3.5 text-indigo-400" /> Tên phiên kiểm tra <span class="text-rose-500">*</span>
                        </label>
                        <Input v-model="inspectionForm.title" placeholder="Ví dụ: Kiểm tra ATTP bếp và kho nguyên liệu tuần 35" class="h-10 rounded-xl" />
                    </div>

                    <!-- Lịch dự kiến -->
                    <div class="space-y-1.5">
                        <label class="flex items-center gap-1.5 text-sm font-semibold text-foreground">
                            <Calendar class="size-3.5 text-indigo-400" /> Thời gian dự kiến
                        </label>
                        <input
                            v-model="inspectionForm.scheduled_at"
                            type="datetime-local"
                            class="h-11 w-full rounded-xl border border-input bg-background/80 px-3 text-sm text-foreground transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30"
                        />
                    </div>

                    <!-- Thanh tra chính -->
                    <div class="space-y-1.5">
                        <label class="flex items-center gap-1.5 text-sm font-semibold text-foreground">
                            <UserCheck class="size-3.5 text-indigo-400" /> Giám sát viên chính
                        </label>
                        <select
                            v-model="inspectionForm.lead_inspector_id"
                            class="h-10 w-full rounded-xl border border-input bg-background/80 px-3 text-sm text-foreground focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500 transition"
                        >
                            <option value="">Tôi trực tiếp phụ trách</option>
                            <option v-for="inspector in inspectors" :key="inspector.id" :value="inspector.id">{{ inspector.name }} ({{ inspector.email }})</option>
                        </select>
                    </div>

                    <!-- Phạm vi -->
                    <div class="space-y-1.5 md:col-span-2">
                        <label class="text-xs font-semibold text-foreground flex items-center gap-1.5">
                            <MapPin class="size-3.5 text-indigo-400" /> Phạm vi / Mục tiêu kiểm tra <span class="text-rose-500">*</span>
                        </label>
                        <textarea
                            v-model="inspectionForm.scope"
                            rows="2"
                            class="w-full rounded-xl border border-input bg-background/80 px-3 py-2.5 text-sm text-foreground placeholder:text-muted-foreground transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30"
                            placeholder="Mô tả cụ thể khu vực kiểm tra, tiêu chuẩn ATTP, mẫu hồ sơ hoặc kho bãi..."
                        />
                    </div>

                    <!-- Ghi chú vị trí -->
                    <div class="space-y-1.5">
                        <label class="flex items-center gap-1.5 text-sm font-semibold text-foreground">
                            <MapPin class="size-3.5 text-muted-foreground" /> Ghi chú vị trí / Điểm nóng
                        </label>
                        <textarea
                            v-model="inspectionForm.location_note"
                            rows="2"
                            class="w-full rounded-xl border border-input bg-background/80 px-3 py-2.5 text-sm text-foreground placeholder:text-muted-foreground transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30"
                            placeholder="Khu vực lấy mẫu, điểm lưu ý đặc biệt..."
                        />
                    </div>
                </div>

                <!-- Custom Interactive Multi-Select cho Người tham gia -->
                <div class="mt-6 space-y-4 rounded-2xl border border-border/80 bg-muted/20 p-5">
                    <div class="flex items-center justify-between">
                        <label class="flex items-center gap-1.5 text-sm font-semibold text-foreground">
                            <Users class="size-4 text-indigo-400" /> Nhân sự tham gia đoàn kiểm tra
                        </label>
                        <span class="text-[11px] font-bold text-indigo-400">
                            Đã chọn {{ inspectionForm.participants.length }} nhân sự
                        </span>
                    </div>

                    <!-- Badges selected -->
                    <div v-if="inspectionForm.participants.length > 0" class="flex flex-wrap gap-1.5 pb-2">
                        <span
                            v-for="pId in inspectionForm.participants"
                            :key="pId"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-indigo-500/30 bg-indigo-500/10 px-2.5 py-1 text-xs font-medium text-indigo-300"
                        >
                            <User class="size-3 text-indigo-400" />
                            {{ getEmployeeName(pId) }}
                            <button class="hover:text-rose-400 transition" @click.prevent="toggleParticipant(pId)">
                                <X class="size-3" />
                            </button>
                        </span>
                    </div>

                    <!-- Filter candidates -->
                    <div class="flex items-center gap-2">
                        <div class="relative flex-1">
                            <Search class="absolute left-3 top-2.5 size-4 text-muted-foreground" />
                            <Input v-model="participantSearch" placeholder="Tìm tên hoặc email nhân sự..." class="h-9 pl-9 text-xs rounded-xl" />
                        </div>
                        <Button
                            v-if="inspectionForm.participants.length > 0"
                            variant="ghost"
                            size="sm"
                            class="h-9 text-xs text-muted-foreground hover:text-foreground"
                            @click="inspectionForm.participants = []"
                        >
                            Bỏ chọn tất cả
                        </Button>
                    </div>

                    <!-- Interactive candidate chips grid -->
                    <div class="max-h-36 overflow-y-auto grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2 pt-1">
                        <div
                            v-for="employee in filteredParticipantCandidates"
                            :key="employee.id"
                            :class="[
                                'flex items-center justify-between rounded-xl border p-2 text-xs cursor-pointer transition select-none',
                                inspectionForm.participants.includes(employee.id)
                                    ? 'border-indigo-500 bg-indigo-500/15 text-indigo-300 font-semibold'
                                    : 'border-border/60 bg-background/50 hover:bg-muted/60 text-muted-foreground'
                            ]"
                            @click="toggleParticipant(employee.id)"
                        >
                            <div class="min-w-0 pr-2">
                                <p class="truncate font-medium text-foreground">{{ employee.name }}</p>
                                <p class="truncate text-[10px] text-muted-foreground">{{ employee.email }}</p>
                            </div>
                            <div
                                :class="[
                                    'flex size-5 shrink-0 items-center justify-center rounded-md border transition',
                                    inspectionForm.participants.includes(employee.id)
                                        ? 'border-indigo-500 bg-indigo-600 text-white'
                                        : 'border-muted-foreground/30 bg-transparent'
                                ]"
                            >
                                <Check v-if="inspectionForm.participants.includes(employee.id)" class="size-3" />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-7 flex justify-end gap-3 border-t border-border/60 pt-5">
                    <Button variant="outline" class="h-11 rounded-xl px-5 text-sm" @click="isCreating = false">Hủy bỏ</Button>
                    <Button :disabled="isSaving" class="h-11 gap-2 rounded-xl bg-indigo-600 px-5 text-sm text-white hover:bg-indigo-500" @click="createInspection">
                        <Save class="size-4" /> {{ isSaving ? 'Đang lưu...' : 'Lưu phiên kiểm tra' }}
                    </Button>
                </div>
            </section>
        </transition>

        <!-- Main Workspace Layout Grid -->
        <section class="grid gap-7 xl:grid-cols-[420px_minmax(0,1fr)]">
            <!-- Sidebar: Danh sách phiên kiểm tra -->
            <Card class="overflow-hidden border-border/80 shadow-md">
                <CardHeader class="border-b border-border/60 bg-muted/20 p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle class="text-lg font-bold text-foreground">Danh sách phiên</CardTitle>
                            <CardDescription class="text-sm">{{ filteredInspections.length }} / {{ pagination?.total ?? inspections.length }} hồ sơ trong phạm vi</CardDescription>
                        </div>
                        <Button variant="ghost" size="icon" class="size-8 rounded-lg text-muted-foreground hover:bg-muted" @click="reload">
                            <RefreshCw class="size-4" />
                        </Button>
                    </div>

                    <!-- Search & Filter Controls -->
                    <div class="mt-4 space-y-3">
                        <div class="relative">
                            <Search class="absolute left-3 top-2.5 size-3.5 text-muted-foreground" />
                            <Input v-model="searchQuery" placeholder="Tìm tên phiên, mã..." class="h-10 rounded-lg pl-9 text-sm" />
                        </div>
                        <div class="flex items-center gap-1.5 overflow-x-auto pb-1 text-xs">
                            <button
                                v-for="st in [
                                    { key: 'all', label: 'Tất cả' },
                                    { key: 'in_progress', label: 'Đang kiểm tra' },
                                    { key: 'planned', label: 'Đã lên lịch' },
                                    { key: 'completed', label: 'Đã xong' }
                                ]"
                                :key="st.key"
                                :class="[
                                    'shrink-0 rounded-lg px-3 py-1.5 font-medium transition',
                                    statusFilter === st.key ? 'bg-indigo-600 text-white shadow-sm' : 'bg-muted/50 text-muted-foreground hover:bg-muted'
                                ]"
                                @click="statusFilter = st.key"
                            >
                                {{ st.label }}
                            </button>
                        </div>
                    </div>
                </CardHeader>

                <CardContent class="max-h-[780px] space-y-3 overflow-y-auto p-4">
                    <button
                        v-for="inspection in filteredInspections"
                        :key="inspection.id"
                        :class="[
                            'group relative w-full overflow-hidden rounded-2xl border p-4 text-left transition',
                            selectedId === inspection.id
                                ? 'border-indigo-500 bg-indigo-500/10 shadow-md ring-1 ring-indigo-500/40'
                                : 'border-border/60 bg-card hover:border-indigo-500/40 hover:bg-muted/30'
                        ]"
                        @click="selectedId = inspection.id"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-mono text-xs font-bold text-indigo-400">{{ inspection.inspection_code }}</span>
                            <Badge variant="outline" :class="['px-2.5 py-1 text-[11px] font-bold', statusClass(inspection.status)]">
                                {{ statusLabel(inspection.status) }}
                            </Badge>
                        </div>

                        <h3 class="mt-2 line-clamp-2 text-sm font-bold text-foreground transition-colors group-hover:text-indigo-400">
                            {{ inspection.title }}
                        </h3>

                        <div class="mt-3 flex items-center justify-between text-xs text-muted-foreground">
                            <span class="flex items-center gap-1 truncate">
                                <MapPin class="size-3 shrink-0 text-indigo-400" /> {{ inspection.branch?.name }}
                            </span>
                            <span class="shrink-0 text-[10px] font-medium bg-muted px-1.5 py-0.5 rounded">
                                {{ inspectionTypeLabels[inspection.inspection_type] || inspection.inspection_type }}
                            </span>
                        </div>

                        <div class="mt-4 grid grid-cols-3 gap-2 text-center text-[11px]">
                            <div class="rounded-lg border border-border/40 bg-muted/40 p-2">
                                <span class="block text-sm font-bold text-foreground">{{ inspection.checklist_count }}</span>
                                <span class="text-muted-foreground">Checklist</span>
                            </div>
                            <div class="rounded-lg border border-border/40 bg-muted/40 p-2">
                                <span class="block text-sm font-bold text-rose-400">{{ inspection.failed_checklist_count }}</span>
                                <span class="text-muted-foreground">Lỗi</span>
                            </div>
                            <div class="rounded-lg border border-border/40 bg-muted/40 p-2">
                                <span class="block text-sm font-bold text-amber-400">{{ inspection.open_actions_count }}</span>
                                <span class="text-muted-foreground">CAPA</span>
                            </div>
                        </div>
                    </button>

                    <div v-if="!filteredInspections.length" class="rounded-2xl border border-dashed border-border p-10 text-center text-sm text-muted-foreground">
                        <ClipboardList class="mx-auto mb-2 size-8 opacity-40" />
                        Chưa có phiên kiểm tra phù hợp.
                    </div>
                </CardContent>
                <div v-if="pagination && pagination.last_page > 1" class="flex items-center justify-between gap-2 border-t border-border/60 px-4 py-3 text-xs">
                    <Button variant="outline" size="sm" class="h-8 rounded-lg text-xs" :disabled="pagination.current_page <= 1" @click="navigateInspectionList(pagination.current_page - 1)">
                        Trước
                    </Button>
                    <div class="flex items-center gap-1">
                        <button
                            v-for="page in paginationPages"
                            :key="page"
                            type="button"
                            :class="['size-8 rounded-lg text-xs font-semibold', page === pagination.current_page ? 'bg-indigo-600 text-white' : 'text-muted-foreground hover:bg-muted']"
                            @click="navigateInspectionList(page)"
                        >
                            {{ page }}
                        </button>
                    </div>
                    <Button variant="outline" size="sm" class="h-8 rounded-lg text-xs" :disabled="pagination.current_page >= pagination.last_page" @click="navigateInspectionList(pagination.current_page + 1)">
                        Sau
                    </Button>
                </div>
            </Card>

            <!-- Main Panel: Chi tiết phiên kiểm tra -->
            <div v-if="selectedInspection" class="space-y-6">
                <!-- Header Card của phiên được chọn -->
                <Card class="border-border/80 shadow-md overflow-hidden">
                    <CardContent class="p-5 md:p-6 space-y-5">
                        <!-- Top Row: Code & Actions -->
                        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between border-b border-border/60 pb-4">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-mono text-xs font-bold text-indigo-400 bg-indigo-500/10 px-2.5 py-1 rounded-lg border border-indigo-500/20">
                                        {{ selectedInspection.inspection_code }}
                                    </span>
                                    <Badge variant="outline" :class="['text-xs font-bold px-2.5 py-0.5', statusClass(selectedInspection.status)]">
                                        {{ statusLabel(selectedInspection.status) }}
                                    </Badge>
                                    <Badge v-if="selectedInspection.risk_level" variant="outline" class="border-rose-500/30 bg-rose-500/10 text-rose-400 text-xs font-bold">
                                        Rủi ro {{ selectedInspection.risk_level?.toUpperCase() }}
                                    </Badge>
                                </div>
                                <h2 class="mt-2 text-xl font-extrabold text-foreground md:text-2xl">{{ selectedInspection.title }}</h2>
                                <p class="mt-1 flex items-center gap-2 text-xs text-muted-foreground">
                                    <Building2 class="size-3.5 text-indigo-400" /> {{ selectedInspection.branch?.name }}
                                    <span>·</span>
                                    <UserCheck class="size-3.5 text-indigo-400" /> Phụ trách: {{ selectedInspection.lead_inspector?.name || 'Tôi phụ trách' }}
                                </p>
                            </div>

                            <!-- Action Toolbar -->
                            <div class="flex flex-wrap items-center gap-2">
                                <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-xl border border-indigo-500/30 bg-indigo-500/10 px-3 py-2 text-xs font-bold text-indigo-300 hover:bg-indigo-500/20 transition">
                                    <Camera class="size-4" /> Tải ảnh bằng chứng
                                    <input type="file" accept="image/*" class="hidden" @change="uploadEvidence" />
                                </label>

                                <Button
                                    v-if="capabilities.execute && ['draft', 'planned'].includes(selectedInspection.status)"
                                    class="gap-1.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs"
                                    @click="startInspection"
                                >
                                    <Play class="size-4" /> Bắt đầu kiểm tra
                                </Button>

                                <Button
                                    v-if="capabilities.execute && selectedInspection.status === 'in_progress'"
                                    variant="outline"
                                    class="gap-1.5 border-emerald-500/40 text-emerald-400 hover:bg-emerald-500/10 rounded-xl text-xs"
                                    @click="completeInspection"
                                >
                                    <CheckCircle2 class="size-4" /> Hoàn tất phiên
                                </Button>

                                <a
                                    :href="'/operations/audit?inspection_id=' + selectedInspection.id + '&branch_id=' + selectedInspection.branch?.id"
                                    class="inline-flex h-9 items-center gap-1.5 rounded-xl border border-rose-500/30 bg-rose-500/10 px-3 text-xs font-bold text-rose-300 hover:bg-rose-500/20 transition"
                                >
                                    <FileWarning class="size-4 text-rose-400" /> Lập biên bản vi phạm
                                </a>
                            </div>
                        </div>

                        <!-- 4 Stat Summary Cards -->
                        <div class="grid gap-3 grid-cols-2 md:grid-cols-4">
                            <div class="rounded-2xl bg-muted/30 border border-border/60 p-3.5 space-y-1">
                                <p class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Phạm vi tác nghiệp</p>
                                <p class="line-clamp-2 text-xs font-medium text-foreground">{{ selectedInspection.scope || 'Toàn bộ cửa hàng' }}</p>
                            </div>

                            <div class="rounded-2xl bg-muted/30 border border-border/60 p-3.5 space-y-1">
                                <p class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Checklist đánh giá</p>
                                <p class="text-2xl font-black text-foreground">{{ selectedInspection.checklist_count }}</p>
                                <p class="text-[10px] text-rose-400 font-medium">{{ selectedInspection.failed_checklist_count }} mục không đạt</p>
                            </div>

                            <div class="rounded-2xl bg-muted/30 border border-border/60 p-3.5 space-y-1">
                                <p class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Số biên bản lập</p>
                                <p class="text-2xl font-black text-foreground">{{ selectedInspection.reports_count }}</p>
                                <p class="text-[10px] text-amber-400 font-medium">{{ selectedInspection.open_reports_count }} chưa đóng</p>
                            </div>

                            <div class="rounded-2xl bg-muted/30 border border-border/60 p-3.5 space-y-1">
                                <p class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Điểm đánh giá</p>
                                <p class="text-2xl font-black text-indigo-400">{{ selectedInspection.score ?? '—' }}</p>
                                <p class="text-[10px] text-muted-foreground">Chốt khi khóa phiên</p>
                            </div>
                        </div>

                        <!-- Liên kết hồ sơ liên quan (Incidents / Assets / Violation Reports) -->
                        <div class="rounded-2xl border border-indigo-500/20 bg-indigo-500/5 p-4 space-y-2">
                            <div class="flex flex-col justify-between gap-3 md:flex-row md:items-center">
                                <div>
                                    <p class="text-xs font-bold text-foreground flex items-center gap-1.5">
                                        <Link2 class="size-4 text-indigo-400" /> Liên kết hồ sơ sự cố / tài sản liên quan
                                    </p>
                                    <p class="text-[11px] text-muted-foreground">Gắn phiên này với sự cố vận hành, biên bản vi phạm hoặc mã tài sản để đối soát tập trung.</p>
                                </div>

                                <div class="flex items-center gap-2">
                                    <select
                                        v-model="caseLinkForm.link_type"
                                        class="h-9 rounded-xl border border-input bg-background/80 px-2.5 text-xs text-foreground"
                                    >
                                        <option value="incident">Sự cố vận hành</option>
                                        <option value="violation_report">Biên bản vi phạm</option>
                                        <option value="fixed_asset">Tài sản cố định</option>
                                    </select>
                                    <Input v-model="caseLinkForm.link_id" type="number" min="1" placeholder="Nhập ID hồ sơ" class="h-9 w-28 text-xs rounded-xl" />
                                    <Button size="sm" class="h-9 rounded-xl text-xs bg-indigo-600 hover:bg-indigo-500 text-white" :disabled="!caseLinkForm.link_id" @click="linkCase">
                                        Liên kết
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Section Checklist Thực Địa -->
                <Card v-if="selectedInspection.status === 'in_progress'" class="border-border/80 shadow-md">
                    <CardHeader class="border-b border-border/60 pb-3">
                        <CardTitle class="flex items-center gap-2 text-base font-bold text-foreground">
                            <ClipboardCheck class="size-5 text-emerald-400" /> Checklist đánh giá tại hiện trường
                        </CardTitle>
                        <CardDescription class="text-xs">
                            Đánh giá Đạt / Không đạt / N/A cho từng tiêu chuẩn. Mục không đạt có thể bổ sung bằng chứng hoặc tạo hành động khắc phục CAPA.
                        </CardDescription>
                    </CardHeader>
                    <button v-if="pendingCount" type="button" class="rounded-lg bg-indigo-500/10 px-3 py-1.5 text-[11px] font-semibold text-indigo-300" @click="retryFailed">
                        {{ pendingCount }} mục chờ đồng bộ<span v-if="failedCount"> · {{ failedCount }} lỗi</span>
                    </button>
                    <CardContent class="space-y-4 pt-4">
                        <div v-for="item in selectedChecklist" :key="item.id" class="rounded-2xl border border-border/70 bg-card p-4 space-y-3 hover:border-indigo-500/30 transition">
                            <div class="flex flex-col justify-between gap-3 md:flex-row md:items-start">
                                <div class="space-y-1">
                                    <Badge variant="outline" class="text-[10px] border-indigo-500/30 bg-indigo-500/10 text-indigo-400">
                                        {{ item.template_name }}
                                    </Badge>
                                    <h4 class="text-sm font-bold text-foreground">{{ item.title }}</h4>
                                    <p v-if="item.description" class="text-xs text-muted-foreground">{{ item.description }}</p>
                                </div>

                                <div v-if="item.completion" class="flex flex-col items-end gap-1 shrink-0">
                                    <Badge :class="item.completion.result === 'fail' ? 'border-rose-500/40 bg-rose-500/10 text-rose-400' : 'border-emerald-500/40 bg-emerald-500/10 text-emerald-400'" variant="outline">
                                        {{ checklistResultLabel(item.completion.result) }}
                                    </Badge>
                                    <span class="text-[10px] text-muted-foreground">{{ item.completion.completed_by || 'Đã ghi nhận' }} · {{ item.completion.completed_at || '' }}</span>
                                </div>
                                <div v-else class="flex items-center gap-1.5 shrink-0">
                                    <Button size="sm" variant="outline" class="h-8 gap-1 border-emerald-500/40 text-emerald-400 hover:bg-emerald-500/20 text-xs rounded-xl" @click="saveChecklist(item, 'pass')">
                                        <Check class="size-3.5" /> Đạt
                                    </Button>
                                    <Button size="sm" variant="outline" class="h-8 gap-1 border-rose-500/40 text-rose-400 hover:bg-rose-500/20 text-xs rounded-xl" @click="saveChecklist(item, 'fail')">
                                        <AlertTriangle class="size-3.5" /> Không đạt
                                    </Button>
                                    <Button size="sm" variant="outline" class="h-8 text-xs rounded-xl text-muted-foreground" @click="saveChecklist(item, 'na')">
                                        N/A
                                    </Button>
                                </div>
                            </div>

                            <div class="grid gap-2.5 md:grid-cols-2 pt-2 border-t border-border/40">
                                <Input v-model="resultNotes[item.id]" placeholder="Ghi chú nhận xét kiểm tra..." class="h-9 text-xs rounded-xl" />
                                <Input
                                    v-model="findingNotes[item.id]"
                                    :placeholder="item.requires_photo ? 'Mô tả sai lệch (bắt buộc ảnh)...' : 'Mô tả sai lệch / ghi chú khuyết điểm...'"
                                    class="h-9 text-xs rounded-xl"
                                />
                            </div>

                            <!-- Single Checklist item photo selection -->
                            <div class="flex items-center justify-between pt-1">
                                <label class="inline-flex items-center gap-2 text-xs font-medium text-muted-foreground cursor-pointer hover:text-indigo-400 transition">
                                    <Camera class="size-4 text-indigo-400" />
                                    <span>{{ checklistPhotos[item.id] ? 'Đã đính kèm ảnh hiện trường' : 'Đính kèm ảnh hiện trường mục này' }}</span>
                                    <input type="file" accept="image/*" class="hidden" @change="setChecklistPhoto(item.id, $event)" />
                                </label>

                                <div v-if="checklistPhotos[item.id]" class="flex items-center gap-2">
                                    <span class="text-[10px] text-emerald-400 font-bold">✓ Ảnh khả dụng</span>
                                    <Button variant="ghost" size="sm" class="h-6 px-1.5 text-[10px] text-rose-400" @click="delete checklistPhotos[item.id]">Xóa</Button>
                                </div>
                            </div>
                        </div>

                        <div v-if="!selectedChecklist.length" class="rounded-2xl border border-dashed border-border p-8 text-center text-xs text-muted-foreground">
                            Chưa có mẫu checklist tiêu chuẩn được cài đặt.
                        </div>
                    </CardContent>
                </Card>

                <!-- Section Kết luận khóa phiên -->
                <Card v-if="selectedInspection.status === 'in_progress'" class="border-border/80 shadow-md">
                    <CardHeader class="pb-3">
                        <CardTitle class="flex items-center gap-2 text-base font-bold text-foreground">
                            <ShieldCheck class="size-5 text-indigo-400" /> Kết luận & Khóa phiên tác nghiệp
                        </CardTitle>
                        <CardDescription class="text-xs">Khóa phiên kiểm tra sau khi đã hoàn tất đánh giá thực địa.</CardDescription>
                    </CardHeader>
                    <CardContent class="grid gap-4 md:grid-cols-[1fr_140px_160px_auto] md:items-end">
                        <div class="space-y-1.5">
                            <label class="text-xs font-semibold text-foreground">Nhận xét / Kết luận chung <span class="text-rose-500">*</span></label>
                            <textarea
                                v-model="completeForm.conclusion"
                                rows="2"
                                class="w-full rounded-xl border border-input bg-background/80 px-3 py-2 text-xs text-foreground placeholder:text-muted-foreground focus:ring-2 focus:ring-indigo-500/30"
                                placeholder="Tổng kết đánh giá tuân thủ chi nhánh..."
                            />
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-xs font-semibold text-foreground">Điểm số (0-100)</label>
                            <Input v-model="completeForm.score" type="number" min="0" max="100" placeholder="100" class="h-10 text-xs rounded-xl" />
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-xs font-semibold text-foreground">Mức độ rủi ro</label>
                            <select v-model="completeForm.risk_level" class="h-10 w-full rounded-xl border border-input bg-background/80 px-3 text-xs text-foreground">
                                <option value="">Tự động tính</option>
                                <option value="low">Thấp</option>
                                <option value="medium">Trung bình</option>
                                <option value="high">Cao</option>
                                <option value="critical">Nghiêm trọng</option>
                            </select>
                        </div>

                        <Button :disabled="isSaving" class="h-10 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl gap-2 text-xs" @click="completeInspection">
                            <CheckCircle2 class="size-4" /> Khóa phiên
                        </Button>
                    </CardContent>
                </Card>

                <!-- Section Bằng chứng hình ảnh đã tải lên -->
                <Card class="border-border/80 shadow-md">
                    <CardHeader class="pb-3 border-b border-border/60">
                        <CardTitle class="flex items-center gap-2 text-base font-bold text-foreground">
                            <ImageIcon class="size-5 text-indigo-400" /> Thư viện bằng chứng hình ảnh hiện trường
                        </CardTitle>
                        <CardDescription class="text-xs">Tệp tin ảnh bằng chứng thực địa có đóng dấu thời gian và mã băm mã hóa SHA-256</CardDescription>
                    </CardHeader>
                    <CardContent class="pt-4">
                        <div v-if="selectedInspection.evidence?.length" class="grid gap-3 grid-cols-1 sm:grid-cols-2 md:grid-cols-3">
                            <div v-for="evidence in selectedInspection.evidence" :key="evidence.id" class="group relative overflow-hidden rounded-2xl border border-border/70 bg-card p-3 space-y-2 hover:border-indigo-500/40 transition">
                                <div class="aspect-video w-full overflow-hidden rounded-xl bg-muted flex items-center justify-center relative">
                                    <img :src="evidence.url" :alt="evidence.original_name" class="size-full object-cover group-hover:scale-105 transition duration-300" />
                                    <a :href="evidence.url" target="_blank" rel="noreferrer" class="absolute inset-0 flex items-center justify-center bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity text-white text-xs font-bold gap-1">
                                        <ExternalLink class="size-4" /> Xem ảnh lớn
                                    </a>
                                </div>
                                <div class="space-y-0.5">
                                    <p class="truncate text-xs font-semibold text-foreground">{{ evidence.original_name }}</p>
                                    <p class="text-[10px] text-muted-foreground">{{ evidence.captured_at || 'Mới cập nhật' }}</p>
                                    <p class="font-mono text-[9px] text-indigo-400 truncate">SHA: {{ evidence.sha256?.slice(0, 16) }}…</p>
                                </div>
                            </div>
                        </div>
                        <p v-else class="text-xs text-muted-foreground text-center py-6">
                            Chưa có hình ảnh bằng chứng nào được tải lên cho phiên kiểm tra này.
                        </p>
                    </CardContent>
                </Card>

                <!-- Section Hành động khắc phục CAPA -->
                <Card class="border-border/80 shadow-md">
                    <CardHeader class="border-b border-border/60 pb-3">
                        <CardTitle class="flex items-center gap-2 text-base font-bold text-foreground">
                            <ClipboardList class="size-5 text-amber-400" /> Kế hoạch & Hành động khắc phục (CAPA)
                        </CardTitle>
                        <CardDescription class="text-xs">Theo dõi tiến độ phân công, nhận việc, sửa chữa sai phạm và xác minh độc lập tại chi nhánh.</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-5 pt-4">
                        <!-- Danh sách CAPA -->
                        <div class="space-y-3">
                            <div v-for="action in selectedInspection.corrective_actions" :key="action.id" class="rounded-2xl border border-border/70 bg-card p-4 space-y-3">
                                <div class="flex flex-col justify-between gap-3 md:flex-row md:items-center">
                                    <div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h4 class="text-sm font-bold text-foreground">{{ action.title }}</h4>
                                            <Badge variant="outline" :class="['text-[10px] font-bold px-2 py-0.5', statusClass(action.status)]">
                                                {{ statusLabel(action.status) }}
                                            </Badge>
                                            <span class="text-[10px] text-muted-foreground font-medium">
                                                Ưu tiên {{ action.priority?.toUpperCase() }} · Hạn chót: {{ action.due_date || 'Chưa đặt' }}
                                            </span>
                                        </div>
                                        <p v-if="action.description" class="mt-1 text-xs text-muted-foreground">{{ action.description }}</p>
                                        <p class="mt-1 text-[11px] text-indigo-400 font-medium">
                                            Người chịu trách nhiệm: {{ action.assignee?.name || 'Chưa phân công' }}
                                        </p>
                                    </div>

                                    <!-- CAPA Action Workflow Buttons -->
                                    <div class="flex flex-wrap gap-1.5 shrink-0">
                                        <Button v-if="action.assigned_to === currentUserId && action.status === 'open'" size="sm" variant="outline" class="h-8 text-xs rounded-xl" @click="updateAction(action, 'accepted')">
                                            Nhận việc
                                        </Button>
                                        <Button v-if="action.assigned_to === currentUserId && ['accepted', 'rejected'].includes(action.status)" size="sm" variant="outline" class="h-8 text-xs rounded-xl" @click="updateAction(action, 'in_progress')">
                                            Bắt đầu
                                        </Button>
                                        <Button v-if="action.assigned_to === currentUserId && action.status === 'in_progress'" size="sm" class="h-8 text-xs bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl" @click="updateAction(action, 'submitted')">
                                            Nộp xác minh
                                        </Button>
                                        <Button v-if="capabilities.verify_actions && action.status === 'submitted' && action.assigned_to !== currentUserId" size="sm" class="h-8 text-xs bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl" @click="updateAction(action, 'verified')">
                                            Xác minh đạt
                                        </Button>
                                        <Button v-if="capabilities.verify_actions && action.status === 'submitted' && action.assigned_to !== currentUserId" size="sm" variant="outline" class="h-8 text-xs text-rose-400 border-rose-500/30 hover:bg-rose-500/10 rounded-xl" @click="updateAction(action, 'rejected')">
                                            Yêu cầu làm lại
                                        </Button>
                                    </div>
                                </div>
                            </div>

                            <div v-if="!selectedInspection.corrective_actions?.length" class="rounded-2xl border border-dashed border-border p-6 text-center text-xs text-muted-foreground">
                                Chưa phát sinh hành động khắc phục CAPA nào cho phiên này.
                            </div>
                        </div>

                        <!-- Biểu mẫu tạo CAPA mới -->
                        <div v-if="capabilities.manage_actions && selectedInspection.status !== 'cancelled'" class="rounded-2xl border border-indigo-500/20 bg-indigo-500/5 p-4 space-y-3">
                            <p class="text-xs font-bold text-foreground flex items-center gap-1.5">
                                <Plus class="size-4 text-indigo-400" /> Khởi tạo hành động khắc phục CAPA mới
                            </p>
                            <div class="grid gap-3 md:grid-cols-2">
                                <Input v-model="actionForm.title" placeholder="Tên hành động khắc phục..." class="h-9 text-xs rounded-xl" />
                                <select v-model="actionForm.assigned_to" class="h-9 rounded-xl border border-input bg-background/80 px-3 text-xs text-foreground">
                                    <option value="">Chưa phân công người phụ trách</option>
                                    <option v-for="employee in branchEmployees" :key="employee.id" :value="employee.id">{{ employee.name }} ({{ employee.email }})</option>
                                </select>
                                <textarea v-model="actionForm.description" rows="2" class="w-full rounded-xl border border-input bg-background/80 px-3 py-2 text-xs text-foreground" placeholder="Mô tả công việc cần xử lý..." />
                                <textarea v-model="actionForm.preventive_action" rows="2" class="w-full rounded-xl border border-input bg-background/80 px-3 py-2 text-xs text-foreground" placeholder="Biện pháp phòng ngừa tái diễn..." />
                                <select v-model="actionForm.priority" class="h-9 rounded-xl border border-input bg-background/80 px-3 text-xs text-foreground">
                                    <option value="low">Ưu tiên thấp</option>
                                    <option value="normal">Ưu tiên bình thường</option>
                                    <option value="high">Ưu tiên cao</option>
                                    <option value="critical">Rất nghiêm trọng</option>
                                </select>
                                <input v-model="actionForm.due_date" type="date" class="h-9 rounded-xl border border-input bg-background/80 px-3 text-xs text-foreground" />
                                <div class="md:col-span-2 flex justify-end">
                                    <Button :disabled="isSaving" class="h-9 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs gap-1.5" @click="createAction">
                                        <Plus class="size-3.5" /> Tạo hành động
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Empty State khi chưa chọn phiên -->
            <Card v-else class="flex min-h-[520px] items-center justify-center border-dashed border-border/80">
                <CardContent class="space-y-4 text-center">
                    <div class="mx-auto flex size-16 items-center justify-center rounded-2xl bg-indigo-500/10 text-indigo-400">
                        <ClipboardCheck class="size-8" />
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-foreground">Chọn một phiên để tác nghiệp</h3>
                        <p class="text-xs text-muted-foreground max-w-sm mx-auto mt-1">
                            Chọn một phiên kiểm tra từ danh sách bên trái hoặc tạo phiên kiểm tra mới để bắt đầu đánh giá thực địa.
                        </p>
                    </div>
                </CardContent>
            </Card>
        </section>
    </div>
</template>

<style scoped>
.inspection-workspace {
    max-width: 1720px;
    padding: 2rem 2.5rem;
}

.inspection-workspace > :not([hidden]) ~ :not([hidden]) {
    margin-top: 2rem;
}

.inspection-workspace > div:first-child {
    min-height: 11.5rem;
    padding: 2rem 2.25rem;
}

.inspection-workspace > div:first-child h1 {
    font-size: clamp(2rem, 2.8vw, 2.75rem);
    line-height: 1.1;
    color: var(--foreground);
}

.inspection-workspace > div:first-child p {
    font-size: 0.9375rem;
    line-height: 1.6;
    color: var(--muted-foreground);
}

.inspection-workspace > div:first-child span {
    color: var(--primary);
}

.inspection-workspace > section:last-child {
    gap: 1.75rem;
}

.inspection-workspace > section:last-child > div {
    min-width: 0;
}

.inspection-workspace [data-slot='card-title'] {
    font-size: 1.125rem;
    line-height: 1.4;
}

.inspection-workspace [data-slot='card-description'] {
    font-size: 0.875rem;
    line-height: 1.5;
}

.inspection-workspace > section:last-child > [data-slot='card']:last-child h3 {
    font-size: 1.125rem;
    line-height: 1.4;
}

.inspection-workspace > section:last-child > [data-slot='card']:last-child p {
    max-width: 32rem;
    font-size: 0.875rem;
    line-height: 1.5;
}

@media (min-width: 1280px) {
    .inspection-workspace > section:last-child {
        grid-template-columns: minmax(390px, 420px) minmax(0, 1fr);
    }
}

@media (max-width: 767px) {
    .inspection-workspace {
        padding: 1rem;
    }

    .inspection-workspace > div:first-child {
        min-height: 0;
        padding: 1.25rem;
    }
}
</style>
