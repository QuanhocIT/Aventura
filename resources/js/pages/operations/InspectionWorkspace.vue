<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import {
    AlertTriangle,
    CalendarClock,
    Check,
    CheckCircle2,
    ClipboardCheck,
    ClipboardList,
    FileWarning,
    MapPin,
    Play,
    Plus,
    RefreshCw,
    ShieldCheck,
    Upload,
    UserRound,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
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
    currentUserId: number;
    capabilities: Record<string, boolean>;
}>();

const selectedId = ref<number | null>(props.inspections[0]?.id ?? null);
const isCreating = ref(false);
const isSaving = ref(false);
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

const selectedInspection = computed(() => props.inspections.find((inspection) => inspection.id === selectedId.value) ?? null);
const branchEmployees = computed(() => {
    const branchId = Number(selectedInspection.value?.branch?.id ?? inspectionForm.value.branch_id);
    return props.employees.filter((employee) => !branchId || Number(employee.branch_id) === branchId);
});
const selectedChecklist = computed(() => {
    const inspection = selectedInspection.value;
    if (!inspection) return [];
    return props.templates.flatMap((template) => template.items.map((item: any) => ({ ...item, template_name: template.name })));
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

function statusLabel(status: string) { return statusLabels[status] ?? status; }
function statusClass(status: string) {
    return {
        draft: 'bg-slate-500/10 text-slate-600 dark:text-slate-300',
        planned: 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-300',
        in_progress: 'bg-amber-500/10 text-amber-700 dark:text-amber-300',
        completed: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
        cancelled: 'bg-rose-500/10 text-rose-600 dark:text-rose-300',
        open: 'bg-slate-500/10 text-slate-600 dark:text-slate-300',
        accepted: 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-300',
        in_progress_action: 'bg-amber-500/10 text-amber-700 dark:text-amber-300',
        submitted: 'bg-violet-500/10 text-violet-700 dark:text-violet-300',
        verified: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
        rejected: 'bg-rose-500/10 text-rose-700 dark:text-rose-300',
    }[status] ?? 'bg-muted text-muted-foreground';
}
function notifyError(error: any) { toast.error(error?.response?.data?.message ?? 'Không thể thực hiện thao tác.'); }
function reload() { router.reload({ only: ['inspections'] }); }

async function createInspection() {
    isSaving.value = true;
    try {
        const response = await axios.post('/api/operational-audit/inspections', inspectionForm.value);
        toast.success(response.data.message);
        isCreating.value = false;
        selectedId.value = response.data.data.id;
        reload();
    } catch (error) { notifyError(error); } finally { isSaving.value = false; }
}
async function startInspection() {
    if (!selectedInspection.value) return;
    try { const response = await axios.post(`/api/operational-audit/inspections/${selectedInspection.value.id}/start`); toast.success(response.data.message); reload(); }
    catch (error) { notifyError(error); }
}
async function completeInspection() {
    if (!selectedInspection.value) return;
    isSaving.value = true;
    try { const response = await axios.post(`/api/operational-audit/inspections/${selectedInspection.value.id}/complete`, completeForm.value); toast.success(response.data.message); reload(); }
    catch (error) { notifyError(error); } finally { isSaving.value = false; }
}
async function saveChecklist(item: any, result: 'pass' | 'fail' | 'na') {
    if (!selectedInspection.value) return;
    try {
        const response = await axios.post(`/api/operational-audit/inspections/${selectedInspection.value.id}/checklist`, {
            item_id: item.id,
            result,
            notes: resultNotes.value[item.id] ?? '',
            finding_notes: findingNotes.value[item.id] ?? '',
            photo: checklistPhotos.value[item.id] ?? '',
        });
        toast.success(response.data.message);
        reload();
    } catch (error) { notifyError(error); }
}
function setChecklistPhoto(itemId: number, event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = () => { checklistPhotos.value[itemId] = String(reader.result ?? ''); };
    reader.readAsDataURL(file);
}
async function createAction() {
    if (!selectedInspection.value) return;
    isSaving.value = true;
    try {
        const response = await axios.post(`/api/operational-audit/inspections/${selectedInspection.value.id}/actions`, actionForm.value);
        toast.success(response.data.message);
        actionForm.value = { title: '', description: '', root_cause: '', corrective_action: '', preventive_action: '', assigned_to: '', priority: 'normal', due_date: '' };
        reload();
    } catch (error) { notifyError(error); } finally { isSaving.value = false; }
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
    } catch (error) { notifyError(error); }
    (event.target as HTMLInputElement).value = '';
}
async function updateAction(action: any, status: string) {
    try { const response = await axios.patch(`/api/operational-audit/actions/${action.id}`, { status, submission_notes: status === 'submitted' ? 'Đã hoàn tất hành động và gửi xác minh.' : undefined, verification_notes: status === 'verified' ? 'Đã kiểm tra kết quả tại hiện trường.' : undefined }); toast.success(response.data.message); reload(); }
    catch (error) { notifyError(error); }
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
    } catch (error) { notifyError(error); }
}
</script>

<template>
    <Head title="Phiên kiểm tra hiện trường" />

    <div class="mx-auto max-w-[1600px] space-y-6 p-4 md:p-6">
        <section class="flex flex-col justify-between gap-4 rounded-3xl border border-indigo-500/20 bg-gradient-to-br from-indigo-500/10 via-card to-card p-6 md:flex-row md:items-center">
            <div class="flex items-start gap-4">
                <div class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-indigo-600 text-white shadow-lg shadow-indigo-600/20"><ClipboardCheck class="size-6" /></div>
                <div>
                    <p class="text-[10px] font-bold tracking-[0.18em] text-indigo-600 uppercase dark:text-indigo-300">Tác nghiệp hiện trường</p>
                    <h1 class="mt-1 text-2xl font-black text-foreground">Phiên kiểm tra & CAPA</h1>
                    <p class="mt-1 max-w-2xl text-sm text-muted-foreground">Mỗi lần kiểm tra có người phụ trách, bằng chứng, checklist, phát hiện và hành động khắc phục riêng. Kết quả đạt cũng được lưu thành hồ sơ.</p>
                </div>
            </div>
            <Button v-if="capabilities.create" class="gap-2" @click="isCreating = !isCreating"><Plus class="size-4" /> Tạo phiên kiểm tra</Button>
        </section>

        <section v-if="isCreating" class="rounded-3xl border border-indigo-500/20 bg-card p-5 shadow-sm md:p-6">
            <div class="flex items-center justify-between"><div><h2 class="text-lg font-bold">Lập phiên kiểm tra mới</h2><p class="text-xs text-muted-foreground">Phiên là hồ sơ thực thi, có thể gắn với một kế hoạch đã được phê duyệt.</p></div><button class="rounded-lg p-2 text-muted-foreground hover:bg-muted" @click="isCreating = false"><X class="size-4" /></button></div>
            <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <label class="space-y-1.5 text-sm font-semibold">Chi nhánh *<select v-model="inspectionForm.branch_id" class="h-10 w-full rounded-xl border border-input bg-background px-3 text-sm"><option value="">Chọn chi nhánh</option><option v-for="branch in branches" :key="branch.id" :value="branch.id">{{ branch.name }}</option></select></label>
                <label class="space-y-1.5 text-sm font-semibold">Kế hoạch liên quan<select v-model="inspectionForm.inspection_plan_id" class="h-10 w-full rounded-xl border border-input bg-background px-3 text-sm"><option value="">Không gắn kế hoạch</option><option v-for="plan in plans" :key="plan.id" :value="plan.id">{{ plan.plan_code }} · {{ plan.title }}</option></select></label>
                <label class="space-y-1.5 text-sm font-semibold">Loại kiểm tra<select v-model="inspectionForm.inspection_type" class="h-10 w-full rounded-xl border border-input bg-background px-3 text-sm"><option value="routine">Định kỳ</option><option value="thematic">Theo chuyên đề</option><option value="surprise">Đột xuất</option><option value="follow_up">Tái kiểm</option></select></label>
                <label class="space-y-1.5 text-sm font-semibold md:col-span-2 xl:col-span-2">Tên phiên *<Input v-model="inspectionForm.title" placeholder="Ví dụ: Kiểm tra ATTP bếp và kho tuần 35" /></label>
                <label class="space-y-1.5 text-sm font-semibold">Lịch dự kiến<input v-model="inspectionForm.scheduled_at" type="datetime-local" class="h-10 w-full rounded-xl border border-input bg-background px-3 text-sm" /></label>
                <label class="space-y-1.5 text-sm font-semibold">Thanh tra chính<select v-model="inspectionForm.lead_inspector_id" class="h-10 w-full rounded-xl border border-input bg-background px-3 text-sm"><option value="">Tôi phụ trách</option><option v-for="inspector in inspectors" :key="inspector.id" :value="inspector.id">{{ inspector.name }} · {{ inspector.email }}</option></select></label>
                <label class="space-y-1.5 text-sm font-semibold md:col-span-2">Phạm vi / mục tiêu *<textarea v-model="inspectionForm.scope" rows="3" class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm" placeholder="Khu vực, tiêu chuẩn, mẫu hồ sơ và nội dung cần kiểm tra..." /></label>
                <label class="space-y-1.5 text-sm font-semibold">Ghi chú vị trí<textarea v-model="inspectionForm.location_note" rows="3" class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm" placeholder="Vị trí lấy mẫu, khu vực hạn chế..." /></label>
            </div>
            <label class="mt-4 block max-w-xl space-y-1.5 text-sm font-semibold">Người tham gia kiểm tra
                <select v-model="inspectionForm.participants" multiple class="min-h-24 w-full rounded-xl border border-input bg-background px-3 py-2 text-sm">
                    <option v-for="employee in employees" :key="employee.id" :value="employee.id">{{ employee.name }} · {{ employee.email }}</option>
                </select>
                <span class="text-xs font-normal text-muted-foreground">Có thể chọn nhiều người; hệ thống lưu rõ người lập, người thực hiện và người xác minh.</span>
            </label>
            <div class="mt-4 flex justify-end gap-2"><Button variant="outline" @click="isCreating = false">Hủy</Button><Button :disabled="isSaving" @click="createInspection">Lưu phiên kiểm tra</Button></div>
        </section>

        <section class="grid gap-4 xl:grid-cols-[380px_1fr]">
            <Card class="overflow-hidden">
                <CardHeader class="border-b border-border/60"><div class="flex items-center justify-between"><div><CardTitle class="text-lg">Danh sách phiên</CardTitle><CardDescription>{{ inspections.length }} phiên trong phạm vi dữ liệu</CardDescription></div><RefreshCw class="size-4 text-muted-foreground" /></div></CardHeader>
                <CardContent class="max-h-[720px] space-y-2 overflow-y-auto p-3">
                    <button v-for="inspection in inspections" :key="inspection.id" :class="['w-full rounded-2xl border p-4 text-left transition', selectedId === inspection.id ? 'border-indigo-500 bg-indigo-500/10 shadow-sm' : 'border-border/70 hover:border-indigo-400/50 hover:bg-muted/30']" @click="selectedId = inspection.id">
                        <div class="flex items-start justify-between gap-2"><span class="font-mono text-[10px] font-bold text-indigo-600 dark:text-indigo-300">{{ inspection.inspection_code }}</span><span :class="['rounded-full px-2 py-1 text-[10px] font-bold', statusClass(inspection.status)]">{{ statusLabel(inspection.status) }}</span></div>
                        <p class="mt-2 line-clamp-2 text-sm font-bold text-foreground">{{ inspection.title }}</p>
                        <p class="mt-2 flex items-center gap-1 text-[11px] text-muted-foreground"><MapPin class="size-3" /> {{ inspection.branch?.name }} <span v-if="inspection.scheduled_at">· {{ inspection.scheduled_at }}</span></p>
                        <div class="mt-3 grid grid-cols-3 gap-2 text-center text-[10px]"><div class="rounded-lg bg-muted/50 p-2"><b class="block text-sm text-foreground">{{ inspection.checklist_count }}</b> mục</div><div class="rounded-lg bg-muted/50 p-2"><b class="block text-sm text-rose-600">{{ inspection.failed_checklist_count }}</b> lỗi</div><div class="rounded-lg bg-muted/50 p-2"><b class="block text-sm text-amber-600">{{ inspection.open_actions_count }}</b> CAPA</div></div>
                    </button>
                    <div v-if="!inspections.length" class="rounded-2xl border border-dashed border-border p-8 text-center text-sm text-muted-foreground"><ClipboardList class="mx-auto mb-2 size-8" />Chưa có phiên kiểm tra.</div>
                </CardContent>
            </Card>

            <div v-if="selectedInspection" class="space-y-4">
                <Card>
                    <CardContent class="p-5 md:p-6">
                        <label class="mb-4 inline-flex cursor-pointer items-center gap-2 rounded-xl border border-border px-3 py-2 text-xs font-bold hover:bg-muted"><Upload class="size-4" /> Tải bằng chứng hiện trường<input type="file" accept="image/*" class="hidden" @change="uploadEvidence" /></label>
                        <div class="flex flex-col justify-between gap-4 md:flex-row md:items-start"><div><div class="flex flex-wrap items-center gap-2"><span class="font-mono text-xs font-bold text-indigo-600 dark:text-indigo-300">{{ selectedInspection.inspection_code }}</span><span :class="['rounded-full px-2 py-1 text-[10px] font-bold', statusClass(selectedInspection.status)]">{{ statusLabel(selectedInspection.status) }}</span><span v-if="selectedInspection.risk_level" class="rounded-full bg-rose-500/10 px-2 py-1 text-[10px] font-bold text-rose-600">Rủi ro {{ selectedInspection.risk_level }}</span></div><h2 class="mt-2 text-xl font-black text-foreground">{{ selectedInspection.title }}</h2><p class="mt-1 text-sm text-muted-foreground">{{ selectedInspection.branch?.name }} · Phụ trách: {{ selectedInspection.lead_inspector?.name || 'Chưa gán' }}</p></div><div class="flex flex-wrap gap-2"><Button v-if="capabilities.execute && ['draft', 'planned'].includes(selectedInspection.status)" class="gap-2" @click="startInspection"><Play class="size-4" /> Bắt đầu</Button><Button v-if="capabilities.execute && selectedInspection.status === 'in_progress'" variant="outline" class="gap-2" @click="completeInspection"><CheckCircle2 class="size-4" /> Hoàn tất phiên</Button><a :href="'/operations/audit?inspection_id=' + selectedInspection.id + '&branch_id=' + selectedInspection.branch?.id" class="inline-flex h-10 items-center gap-2 rounded-xl border border-border px-4 text-xs font-bold hover:bg-muted"><FileWarning class="size-4" /> Lập biên bản</a></div></div>
                        <div class="mt-5 grid gap-3 md:grid-cols-4"><div class="rounded-2xl bg-muted/40 p-3"><p class="text-[10px] font-bold text-muted-foreground uppercase">Phạm vi</p><p class="mt-1 line-clamp-3 text-xs text-foreground">{{ selectedInspection.scope }}</p></div><div class="rounded-2xl bg-muted/40 p-3"><p class="text-[10px] font-bold text-muted-foreground uppercase">Checklist</p><p class="mt-1 text-2xl font-black text-foreground">{{ selectedInspection.checklist_count }}</p><p class="text-[10px] text-muted-foreground">{{ selectedInspection.failed_checklist_count }} mục không đạt</p></div><div class="rounded-2xl bg-muted/40 p-3"><p class="text-[10px] font-bold text-muted-foreground uppercase">Phát hiện</p><p class="mt-1 text-2xl font-black text-foreground">{{ selectedInspection.reports_count }}</p><p class="text-[10px] text-muted-foreground">{{ selectedInspection.open_reports_count }} hồ sơ mở</p></div><div class="rounded-2xl bg-muted/40 p-3"><p class="text-[10px] font-bold text-muted-foreground uppercase">Điểm</p><p class="mt-1 text-2xl font-black text-foreground">{{ selectedInspection.score ?? '—' }}</p><p class="text-[10px] text-muted-foreground">Kết luận khi đóng phiên</p></div></div>
                        <div class="mt-4 rounded-2xl border border-amber-500/20 bg-amber-500/5 p-4"><div class="flex flex-col justify-between gap-3 md:flex-row md:items-end"><div><p class="text-sm font-bold text-foreground">Liên kết hồ sơ liên quan</p><p class="mt-1 text-xs text-muted-foreground">Gắn phiên kiểm tra với sự cố, biên bản vi phạm hoặc tài sản để truy nguyên một đầu mối.</p></div><div class="flex flex-col gap-2 sm:flex-row"><select v-model="caseLinkForm.link_type" class="h-10 rounded-xl border border-input bg-background px-3 text-sm"><option value="incident">Sự cố</option><option value="violation_report">Biên bản vi phạm</option><option value="fixed_asset">Tài sản</option></select><Input v-model="caseLinkForm.link_id" type="number" min="1" placeholder="ID hồ sơ" class="sm:w-32" /><Button size="sm" :disabled="!caseLinkForm.link_id" @click="linkCase">Liên kết</Button></div></div></div>
                    </CardContent>
                </Card>

                <Card v-if="selectedInspection.status === 'in_progress'">
                    <CardHeader><CardTitle class="flex items-center gap-2 text-lg"><ClipboardCheck class="size-5 text-emerald-600" /> Checklist tại hiện trường</CardTitle><CardDescription>Ghi nhận đạt / không đạt / không áp dụng; mục không đạt cần tạo phát hiện hoặc CAPA.</CardDescription></CardHeader>
                    <CardContent class="space-y-3">
                        <div v-for="item in selectedChecklist" :key="item.id" class="rounded-2xl border border-border/70 p-4"><div class="flex flex-col justify-between gap-3 md:flex-row md:items-start"><div class="min-w-0"><p class="text-[10px] font-bold text-indigo-600 uppercase dark:text-indigo-300">{{ item.template_name }}</p><p class="mt-1 text-sm font-bold text-foreground">{{ item.title }}</p><p v-if="item.description" class="mt-1 text-xs text-muted-foreground">{{ item.description }}</p></div><div class="flex shrink-0 gap-1"><Button size="sm" variant="outline" class="h-8 gap-1 border-emerald-300 text-emerald-700" @click="saveChecklist(item, 'pass')"><Check class="size-3" /> Đạt</Button><Button size="sm" variant="outline" class="h-8 gap-1 border-rose-300 text-rose-700" @click="saveChecklist(item, 'fail')"><AlertTriangle class="size-3" /> Không đạt</Button><Button size="sm" variant="outline" class="h-8 h-8 text-xs" @click="saveChecklist(item, 'na')">N/A</Button></div></div><div class="mt-3 grid gap-2 md:grid-cols-2"><Input v-model="resultNotes[item.id]" placeholder="Ghi chú kiểm tra..." /><Input v-model="findingNotes[item.id]" :placeholder="item.requires_photo ? 'Mô tả sai lệch (bắt buộc khi không đạt)...' : 'Mô tả sai lệch / bằng chứng cần bổ sung...'" /></div><label v-if="item.requires_photo" class="mt-2 inline-flex items-center gap-2 text-xs font-semibold text-muted-foreground">Ảnh hiện trường bắt buộc khi không đạt<input type="file" accept="image/*" @change="setChecklistPhoto(item.id, $event)" /></label></div>
                        <div v-if="!selectedChecklist.length" class="rounded-2xl border border-dashed border-border p-8 text-center text-sm text-muted-foreground">Chưa có mẫu checklist hoạt động cho nhà hàng.</div>
                    </CardContent>
                </Card>

                <Card v-if="selectedInspection.status === 'in_progress'">
                    <CardHeader><CardTitle class="flex items-center gap-2 text-lg"><ShieldCheck class="size-5 text-indigo-600" /> Kết luận phiên kiểm tra</CardTitle><CardDescription>Đóng phiên sau khi đã ghi đủ các mục kiểm tra. Biên bản/CAPA vẫn tiếp tục theo dõi độc lập.</CardDescription></CardHeader>
                    <CardContent class="grid gap-3 md:grid-cols-[1fr_160px_180px_auto] md:items-end"><label class="space-y-1 text-xs font-bold">Kết luận *<textarea v-model="completeForm.conclusion" rows="2" class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm font-normal" placeholder="Kết luận, điểm cần theo dõi..." /></label><label class="space-y-1 text-xs font-bold">Điểm<input v-model="completeForm.score" type="number" min="0" max="100" class="h-10 w-full rounded-xl border border-input bg-background px-3 text-sm font-normal" /></label><label class="space-y-1 text-xs font-bold">Mức rủi ro<select v-model="completeForm.risk_level" class="h-10 w-full rounded-xl border border-input bg-background px-3 text-sm font-normal"><option value="">Tự tính</option><option value="low">Thấp</option><option value="medium">Trung bình</option><option value="high">Cao</option><option value="critical">Nghiêm trọng</option></select></label><Button :disabled="isSaving" @click="completeInspection">Khóa phiên</Button></CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle class="flex items-center gap-2 text-lg"><Upload class="size-5 text-indigo-600" /> Bằng chứng đã thu thập</CardTitle><CardDescription>Ảnh được lưu riêng tư, có mã SHA-256 và chỉ tải được trong đúng nhà hàng.</CardDescription></CardHeader>
                    <CardContent>
                        <div v-if="selectedInspection.evidence?.length" class="grid gap-2 md:grid-cols-2">
                            <div v-for="evidence in selectedInspection.evidence" :key="evidence.id" class="flex items-center justify-between gap-3 rounded-xl border border-border/70 p-3">
                                <div class="min-w-0"><p class="truncate text-sm font-semibold">{{ evidence.original_name }}</p><p class="text-[11px] text-muted-foreground">{{ evidence.collection }} · {{ evidence.captured_at || 'Không rõ thời điểm' }} · SHA {{ evidence.sha256?.slice(0, 12) }}…</p></div>
                                <a :href="evidence.url" target="_blank" rel="noreferrer" class="shrink-0 text-xs font-bold text-indigo-600 hover:underline">Mở ảnh</a>
                            </div>
                        </div>
                        <p v-else class="text-sm text-muted-foreground">Chưa có bằng chứng. Hãy tải ảnh ngay trong lúc kiểm tra.</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle class="flex items-center gap-2 text-lg"><ClipboardList class="size-5 text-amber-600" /> Hành động khắc phục / CAPA</CardTitle><CardDescription>Phân công, nhận việc, nộp kết quả và xác minh độc lập; không tự xác minh kết quả của mình.</CardDescription></CardHeader>
                    <CardContent class="space-y-4">
                        <div class="space-y-2"><div v-for="action in selectedInspection.corrective_actions" :key="action.id" class="rounded-2xl border border-border/70 p-4"><div class="flex flex-col justify-between gap-3 md:flex-row md:items-center"><div><div class="flex flex-wrap items-center gap-2"><span class="text-sm font-bold text-foreground">{{ action.title }}</span><span :class="['rounded-full px-2 py-1 text-[10px] font-bold', statusClass(action.status)]">{{ statusLabel(action.status) }}</span><span class="text-[10px] text-muted-foreground">Ưu tiên {{ action.priority }} · Hạn {{ action.due_date || 'Chưa đặt' }}</span></div><p class="mt-1 text-xs text-muted-foreground">{{ action.assignee?.name || 'Chưa phân công' }}</p></div><div class="flex flex-wrap gap-2"><Button v-if="action.assigned_to === currentUserId && action.status === 'open'" size="sm" variant="outline" @click="updateAction(action, 'accepted')">Nhận việc</Button><Button v-if="action.assigned_to === currentUserId && ['accepted', 'rejected'].includes(action.status)" size="sm" variant="outline" @click="updateAction(action, 'in_progress')">Bắt đầu</Button><Button v-if="action.assigned_to === currentUserId && action.status === 'in_progress'" size="sm" @click="updateAction(action, 'submitted')">Nộp xác minh</Button><Button v-if="capabilities.verify_actions && action.status === 'submitted' && action.assigned_to !== currentUserId" size="sm" class="bg-emerald-600 hover:bg-emerald-700" @click="updateAction(action, 'verified')">Xác minh đạt</Button><Button v-if="capabilities.verify_actions && action.status === 'submitted' && action.assigned_to !== currentUserId" size="sm" variant="outline" class="text-rose-600" @click="updateAction(action, 'rejected')">Yêu cầu làm lại</Button></div></div></div><div v-if="!selectedInspection.corrective_actions.length" class="rounded-2xl border border-dashed border-border p-8 text-center text-sm text-muted-foreground">Chưa có CAPA. Có thể tạo ngay khi phát hiện rủi ro từ checklist.</div></div>
                        <div v-if="capabilities.manage_actions && selectedInspection.status !== 'cancelled'" class="rounded-2xl border border-indigo-500/20 bg-indigo-500/5 p-4"><p class="text-sm font-bold text-foreground">Tạo CAPA cho phiên</p><div class="mt-3 grid gap-2 md:grid-cols-2"><Input v-model="actionForm.title" placeholder="Tên hành động khắc phục" /><select v-model="actionForm.assigned_to" class="h-10 rounded-xl border border-input bg-background px-3 text-sm"><option value="">Chưa phân công</option><option v-for="employee in branchEmployees" :key="employee.id" :value="employee.id">{{ employee.name }} · {{ employee.email }}</option></select><textarea v-model="actionForm.description" rows="2" class="rounded-xl border border-input bg-background px-3 py-2 text-sm" placeholder="Mô tả kết quả cần đạt / cách khắc phục" /><textarea v-model="actionForm.preventive_action" rows="2" class="rounded-xl border border-input bg-background px-3 py-2 text-sm" placeholder="Biện pháp phòng ngừa tái diễn" /><select v-model="actionForm.priority" class="h-10 rounded-xl border border-input bg-background px-3 text-sm"><option value="low">Ưu tiên thấp</option><option value="normal">Bình thường</option><option value="high">Ưu tiên cao</option><option value="critical">Nghiêm trọng</option></select><input v-model="actionForm.due_date" type="date" class="h-10 rounded-xl border border-input bg-background px-3 text-sm" /><Button class="md:col-span-2 md:justify-self-end" :disabled="isSaving" @click="createAction"><Plus class="size-4" /> Tạo hành động</Button></div></div>
                    </CardContent>
                </Card>
            </div>
            <Card v-else class="flex min-h-[400px] items-center justify-center"><CardContent class="text-center"><ClipboardCheck class="mx-auto size-10 text-muted-foreground" /><p class="mt-3 font-bold">Chọn một phiên để tác nghiệp</p><p class="mt-1 text-sm text-muted-foreground">Tạo phiên kiểm tra rồi bắt đầu ghi checklist và bằng chứng tại hiện trường.</p></CardContent></Card>
        </section>
    </div>
</template>
